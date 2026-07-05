# Design — Multitenancy Foundation (Backend)

Class-level architecture for converting the single-store catalog into a shared-DB,
row-level `tenant_id` SaaS backend. The tenant filter is enforced by a hand-rolled
Eloquent global scope so it can never be forgotten in a query. This document is the
architectural HOW; it is implementable by `sdd-apply` under strict TDD without any
further architecture decisions.

- **Isolation model**: shared DB, shared schema, row-level `tenant_id` + global scope.
- **Scoped models**: `Product`, `Category`, `Setting`. `User` stays UNSCOPED.
- **Two resolution paths, one scope**: admin (tenant from token), public (tenant from validated slug).
- **Source of truth**: `App\Support\TenantContext` (request-scoped, Octane-safe).
- **Locked decisions** (do not re-litigate): see proposal `sdd/multitenancy-foundation/proposal` and decision `architecture/multitenancy-model` (#746).

---

## 1. Architecture overview

The tenant is resolved once per request by middleware, written into a request-scoped
`TenantContext`, and read passively by `TenantScope` on every query against a scoped
model. The client never supplies the tenant via header, body, or query — only via the
authenticated token (admin) or a validated active slug in the URL (public).

### Verified environment facts (Laravel 13, from real files)

| Fact | Evidence | Consequence for design |
|------|----------|------------------------|
| Middleware config lives in `bootstrap/app.php` `->withMiddleware()` | `bootstrap/app.php` is the Laravel 11+ style; no `app/Http/Kernel.php` exists | Register aliases via `$middleware->alias([...])` |
| `SlugGenerator` uses `DB::table($table)` (query builder, not Eloquent) | `app/Services/SlugGenerator.php:37` | It BYPASSES the global scope — must pass `tenant_id` explicitly (see §2.6) |
| Product/Category slug is globally unique | `2026_05_25_151103` + `2026_05_31_000001` migrations (`->unique()`) | Must drop and replace with composite `(tenant_id, slug)` |
| `StoreProductRequest` uses `exists:categories,id` / `exists:products,id` | `app/Http/Requests/StoreProductRequest.php:17,26` | Cross-tenant leak — must scope validators (see §5) |
| Test + default driver is SQLite | `phpunit.xml:26-27`, `config/database.php:20` | Partial unique index `WHERE deleted_at IS NULL` is supported (SQLite 3.8+) |
| `User` uses attribute-based `#[Fillable]`/`#[Hidden]` | `app/Models/User.php:14-15` | `tenant_id` must be excluded from that attribute; `role` too |
| Route-model binding uses slug (`getRouteKeyName`) | `Product.php:44`, `Category.php:26` | Binding runs through the scoped Eloquent query → foreign slug auto-404s |

### Path A — Admin (token-resolved, read + write)

```
POST /api/products  (Bearer token)
        |
   auth:sanctum ............... authenticates user, sets $request->user()
        |
   ResolveTenant:admin ....... reads user.tenant_id, loads active Tenant,
        |                       404/401 if tenant missing or not active,
        |                       TenantContext::setCurrent($tenant)
        |
  [EnforceTenantMatch] ....... only on admin routes that carry a {tenant} slug:
        |                       403 if URL slug != token tenant slug
        |
   Controller / FormRequest .. Eloquent queries auto-filtered by TenantScope;
        |                       Product::create() auto-fills tenant_id (creating hook)
        |
   terminate ................. TenantContext::forgetCurrent() (middleware terminable)
```

### Path B — Public (slug-resolved, read-only)

```
GET /api/public/{tenant}/products   (no token)
        |
   ResolveTenant:public ...... validates {tenant} slug -> active Tenant lookup
        |                       (withoutTenancy, status=active, not soft-deleted),
        |                       404 if not found/inactive; TenantContext::setCurrent($tenant)
        |
   throttle:public-catalog ... per-IP read throttle (enumeration mitigation)
        |
   Controller ................ Eloquent queries auto-filtered by TenantScope;
        |                       returns active products of that tenant only
        |
   terminate ................. TenantContext::forgetCurrent()
```

Where the context is set / read / cleared:

- **Set**: exactly one place per path — inside `ResolveTenant` (admin or public mode).
- **Read**: passively, inside `TenantScope::apply()` on every scoped-model query.
- **Cleared**: `TenantContext::forgetCurrent()` in the middleware `terminate()` hook and, defensively, `scoped()` binding gives every request a fresh instance under Octane.

Non-request contexts (console, seeders, queued jobs) have NO active tenant, so
`TenantScope` is a no-op there by design. There are no jobs today; this is a documented
deferral, not an omission.

---

## 2. Component design

### 2.1 `App\Support\TenantContext`

Holds the resolved tenant for the current request. Bound with `scoped()` (NOT
`singleton()`) so Laravel Octane rebuilds it per request and no tenant bleeds between
requests served by the same long-lived worker.

```php
namespace App\Support;

use App\Models\Tenant;

class TenantContext
{
    private ?Tenant $tenant = null;

    public function setCurrent(Tenant $tenant): void;   // store resolved tenant
    public function current(): ?Tenant;                 // null when unresolved
    public function currentId(): ?int;                  // $tenant?->id
    public function hasTenant(): bool;                  // current() !== null
    public function checkCurrent(): Tenant;             // current() or throw TenantContextMissingException
    public function forgetCurrent(): void;              // $this->tenant = null
    public function makeCurrent(Tenant $tenant): Tenant; // setCurrent + return (fluent, for tests/seeders)
}
```

Rationale — `scoped()` vs `singleton()`: a `singleton()` is resolved once and reused for
the entire worker lifetime under Octane, so request N+1 would inherit request N's tenant.
`scoped()` resets the instance at the start of each request (Octane flushes scoped
instances between requests), giving natural per-request isolation. `forgetCurrent()` on
terminate is the belt-and-suspenders second line of defense.

`TenantContextMissingException` is thrown by `checkCurrent()` only where a tenant is
required but absent — it is not thrown by the scope (the scope no-ops instead).

### 2.2 `App\Models\Scopes\TenantScope implements Scope`

Adds the tenant filter to every query on a model using `BelongsToTenant`. No-ops when
there is no active tenant (console/seeders/jobs) so those contexts are not accidentally
blocked. Uses the QUALIFIED column `{table}.tenant_id` to stay unambiguous under joins.

```php
namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(\App\Support\TenantContext::class);
        if (! $context->hasTenant()) {
            return; // no-op outside a resolved request
        }
        $builder->where(
            $model->getTable().'.'.$model->getQualifiedTenantColumn(),
            $context->currentId()
        );
    }

    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenancy', fn (Builder $b): Builder =>
            $b->withoutGlobalScope(static::class)
        );
    }
}
```

- Qualified column closes the ambiguous-column risk when `Product` joins `categories`.
- `withoutTenancy()` is the explicit, greppable escape hatch (admin cross-tenant reports
  in a future cycle, or the public active-tenant lookup itself). Its use on a scoped model
  is a review red flag by convention.

### 2.3 `App\Models\Concerns\BelongsToTenant` trait

Wires the scope and auto-fills `tenant_id` on insert. `tenant_id` is deliberately kept OUT
of `$fillable` so it can never be mass-assigned from request input — it is set only by the
`creating` hook from the trusted context.

```php
namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            if ($model->tenant_id === null) {
                $model->tenant_id = app(TenantContext::class)->currentId();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function getQualifiedTenantColumn(): string
    {
        return 'tenant_id';
    }
}
```

- Named boot method (`bootBelongsToTenant`) is Eloquent's trait-boot convention; it fires
  once per model boot.
- The `creating` hook is idempotent-safe: it only fills when `tenant_id` is null, so
  seeders/tests that set it explicitly are respected.
- If the context has no tenant and the column is `NOT NULL`, the insert fails loudly at the
  DB layer — a fail-closed default (better than silently writing a tenant-less row).

### 2.4 `App\Http\Middleware\ResolveTenant`

Decision: **one class with a constructor/route mode parameter**, not two classes. The
resolution SINK (write to `TenantContext`) is identical; only the SOURCE differs (token vs
slug). A single class keeps the "one place that sets the tenant" invariant literally true
and avoids divergence. The mode is supplied via the alias parameter syntax
`resolve.tenant:admin` / `resolve.tenant:public`.

```php
namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;

class ResolveTenant
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next, string $mode = 'admin')
    {
        $tenant = $mode === 'public'
            ? $this->fromSlug($request->route('tenant'))
            : $this->fromToken($request->user());

        // abort(404) when null (public) / abort(401) when null (admin) — see below
        $this->context->setCurrent($tenant);

        return $next($request);
    }

    private function fromToken(?\App\Models\User $user): Tenant;   // active-tenant lookup by user.tenant_id, else 401
    private function fromSlug(?string $slug): Tenant;              // withoutTenancy active-slug lookup, else 404
}
```

Both private resolvers query `Tenant` with `where('status', 'active')->whereNull('deleted_at')`
(the default query already excludes soft-deleted; the `status` check is the explicit gate).
A soft-deleted or suspended tenant therefore fails resolution:

- Admin path: token user's tenant is inactive → `401` (token effectively dead; see §7).
- Public path: slug points to an inactive/missing tenant → `404` (no existence oracle).

The `Tenant` lookup uses `withoutTenancy()` implicitly because `Tenant` itself is NOT a
`BelongsToTenant` model, so no scope applies — but resolution runs BEFORE any tenant is
set anyway.

### 2.5 `App\Http\Middleware\EnforceTenantMatch`

Applies only to admin routes that carry a `{tenant}` slug segment (none in v1's core CRUD,
but reserved for any admin URL that echoes the slug). Returns `403` when the URL slug does
not equal the token tenant's slug — closing the "authenticated user pokes another tenant's
slug" IDOR at the routing layer.

```php
namespace App\Http\Middleware;

class EnforceTenantMatch
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next)
    {
        $urlSlug   = $request->route('tenant');
        $tokenSlug = $this->context->checkCurrent()->slug;

        abort_if($urlSlug !== null && $urlSlug !== $tokenSlug, 403);

        return $next($request);
    }
}
```

Runs AFTER `ResolveTenant:admin` so the context is guaranteed populated.

### 2.6 Signup — `RegisterController` + `RegisterRequest` + `TenantSignupService`

Signup is the one write that creates a tenant, its owner user, seed settings, and a token
in a single atomic transaction. It runs OUTSIDE any tenant context (no tenant exists yet).

```php
// RegisterRequest — global-unique email + slug shape validation
public function rules(): array
{
    return [
        'name'      => ['required', 'string', 'max:255'],   // person / store display name
        'store_name'=> ['required', 'string', 'max:255'],   // used to derive slug
        'email'     => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        'password'  => ['required', 'string', 'min:8', 'confirmed'],
    ];
}

// TenantSignupService — atomic action
public function register(array $data): array   // returns ['tenant'=>..., 'user'=>..., 'token'=>plaintext]
{
    return DB::transaction(function () use ($data) {
        $slug   = $this->slugs->forTenant($data['store_name']);      // reserved-blocklist + collision safe
        $tenant = Tenant::create([
            'name' => $data['store_name'], 'slug' => $slug, 'status' => 'active',
        ]);
        $user = new User(['name' => $data['name'], 'email' => $data['email']]);
        $user->password  = Hash::make($data['password']);            // set outside fillable
        $user->tenant_id = $tenant->id;                              // set outside fillable
        $user->role      = 'owner';                                  // set outside fillable
        $user->save();

        TenantContext::setCurrent($tenant);        // so seeded settings auto-fill tenant_id
        $this->seedDefaultSettings($tenant);

        $token = $user->createToken('admin')->plainTextToken;
        return compact('tenant', 'user', 'token');
    });
}
```

- `RegisterController::store()` calls the service, catches `QueryException` on the users
  unique-email constraint (race that slips past validation) and maps it to a `422` with the
  frontend error envelope, then returns `201` with `{token, user}` mirroring login's shape.
- **Slug generation for tenants** is a NEW method, because the existing `SlugGenerator`
  targets a `slug` column via `DB::table()` and has no reserved-word logic. Add
  `SlugGenerator::forTenant(string $storeName): string` (or a dedicated `TenantSlugGenerator`)
  that: (1) `Str::slug`, (2) rejects/replaces any slug in a `RESERVED_SLUGS` blocklist
  (`admin`, `api`, `public`, `auth`, `app`, `www`, `login`, `register`, `settings`, ...),
  (3) resolves collisions against `tenants.slug` with numeric suffixes. Because `tenants`
  is unscoped and queried via `DB::table('tenants')`, no tenant context is needed here.

### 2.7 Service provider wiring — `AppServiceProvider`

All wiring goes in the existing `AppServiceProvider` (no new provider needed):

- `register()`: `$this->app->scoped(TenantContext::class);` — the Octane-safe binding.
- `boot()`: keep `JsonResource::withoutWrapping()`; extend `configureRateLimiting()` with a
  `signup` limiter (mirroring `login`, keyed by IP) and a `public-catalog` limiter (per-IP
  read throttle for the public path). The `TenantScope` macro (`withoutTenancy`) is
  registered automatically via the scope's `extend()` when it is added to a model, so no
  separate boot registration is required.

```php
// register()
$this->app->scoped(\App\Support\TenantContext::class);

// boot() -> configureRateLimiting()
RateLimiter::for('signup', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
RateLimiter::for('public-catalog', fn (Request $r) => Limit::perMinute(60)->by($r->ip()));
```

---

## 3. Data model / migrations

### 3.1 `tenants` table (NEW migration)

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `name` | string | store display name |
| `slug` | string, **unique** | public URL segment; global unique (tenants are not tenant-scoped) |
| `status` | string, default `active` | `active` in v1; `suspended`/`deactivated` reserved for later cycle |
| `deleted_at` | timestamp nullable | soft delete (`$table->softDeletes()`) |
| `timestamps` | | |

Index: `slug` unique + an index on `status` for the active-tenant lookups.

### 3.2 `tenant_id` on `products`, `categories`, `settings`

Add via a **new migration** (see §3.5 decision), each as:

```php
$table->foreignId('tenant_id')
      ->after('id')
      ->constrained('tenants')
      ->restrictOnDelete();   // justify below
$table->index('tenant_id');
```

`NOT NULL` (default for `foreignId` without `->nullable()`) — fresh start, no backfill, so
every row is born with a tenant.

**`restrictOnDelete` vs `cascadeOnDelete`** — choose **restrict**. In a soft-delete world
the tenant row is never physically `DELETE`d in v1 (only `deleted_at` is set), so a cascade
FK would never fire anyway; declaring `restrict` documents intent and fail-closes if a
future hard-delete is attempted without first purging children (the hard-purge cycle will
add an explicit ordered teardown). Cascade would silently mass-delete on a stray hard
delete — the opposite of what a careful multi-tenant teardown wants.

### 3.3 Composite slug uniqueness (soft-delete aware)

Replace the global `slug` unique on `products` and `categories` with a composite unique on
`(tenant_id, slug)` that ONLY covers live rows, so a soft-deleted slug can be reused within
the same tenant:

```php
// products & categories migration
$table->dropUnique(['slug']);                 // drop the global unique from birth/extension migration
// SQLite (test) + Postgres/MySQL 8: partial unique index
$table->unique(['tenant_id', 'slug'])
      ->whereNull('deleted_at');              // partial index — live rows only
```

If the target production driver does not support partial indexes on this path, fall back to
a plain `unique(['tenant_id', 'slug', 'deleted_at'])` composite (NULL `deleted_at` still
collides per driver semantics — acceptable, but the partial index is preferred and SQLite
tests support it). `sdd-apply` uses the partial-index form; the raw-SQL variant is:
`CREATE UNIQUE INDEX ... ON products (tenant_id, slug) WHERE deleted_at IS NULL`.

`settings` gets composite unique `(tenant_id, key)` (drop the global `key` unique).

### 3.4 `users.role` and `users.tenant_id`

- `users.tenant_id`: `foreignId('tenant_id')->nullable()->constrained('tenants')`.
  Nullable because `User` is UNSCOPED and a future platform super-admin may have no tenant;
  in v1 every signed-up owner has one. Owner tenancy is enforced in `ResolveTenant` +
  signup, not by a global scope.
- `users.role`: `string('role')->default('owner')`. Owner-only in v1; the column exists so
  policies can gate without a later schema change.

`tenant_id` and `role` must be excluded from `User`'s `#[Fillable]` attribute — they are set
explicitly in the signup service, never mass-assigned.

### 3.5 EDIT birth migrations vs ADD new — decision

**ADD new migrations; do NOT edit the birth/extension migrations.** Even though it is a
fresh start, the `2026_05_25` birth migrations and the `2026_05_31` reconcile/settings
migrations have already run in existing dev/CI databases and are part of committed history.
Editing an already-run migration is a footgun (no re-run without a full `migrate:fresh`, and
it rewrites recorded history). New forward migrations are the Laravel-idiomatic, review-
friendly path and keep the tenancy change auditable as its own unit. New files:

1. `..._create_tenants_table.php`
2. `..._add_tenant_id_to_products_table.php` (adds FK, drops global slug unique, adds partial composite)
3. `..._add_tenant_id_to_categories_table.php` (same shape)
4. `..._add_tenant_id_to_settings_table.php` (adds FK, drops global key unique, adds composite `(tenant_id, key)`)
5. `..._add_tenant_id_and_role_to_users_table.php`

Exception: if `migrate:fresh` is the only environment (no shared DB to preserve) the team
MAY instead fold `tenant_id` into the birth migrations — but the default and safer path for
this change is additive migrations.

---

## 4. Middleware registration & routing

### 4.1 `bootstrap/app.php` aliases (Laravel 13 style)

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'resolve.tenant'  => \App\Http\Middleware\ResolveTenant::class,
        'tenant.match'    => \App\Http\Middleware\EnforceTenantMatch::class,
    ]);
})
```

`ResolveTenant` and `EnforceTenantMatch` are terminable (`terminate()` calls
`TenantContext::forgetCurrent()`), so no global-middleware registration is needed — they are
applied per route group.

### 4.2 `routes/api.php` sketch

```php
// --- Public auth (unchanged), rate limited ---
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/auth/register', [RegisterController::class, 'store'])->middleware('throttle:signup');

// --- Admin group: token -> tenant resolved from token ---
Route::middleware(['auth:sanctum', 'resolve.tenant:admin'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('products', ProductController::class)->withTrashed(['show']);

    // Settings endpoints join here when added (also scoped by TenantScope).
});

// --- Public catalog group: NO auth, tenant resolved from validated slug, read-only ---
Route::prefix('public/{tenant}')
    ->middleware(['resolve.tenant:public', 'throttle:public-catalog'])
    ->group(function () {
        Route::get('products', [PublicProductController::class, 'index']);
        Route::get('products/{product}', [PublicProductController::class, 'show']);
        Route::get('categories', [PublicCategoryController::class, 'index']);
    });
```

- `{tenant}` is a raw slug string (not route-model-bound) so `ResolveTenant:public` controls
  the exact active-only lookup and the 404 semantics, rather than a default binding.
- The public group is read-only (only `GET`); no write verbs are registered there.
- `EnforceTenantMatch` (`tenant.match`) is NOT needed on the current admin routes because
  none carry a `{tenant}` segment; it is wired only if such a route is added.

---

## 5. Risk mitigations (mechanism + proving test)

| Risk | Concrete mechanism | Proving test |
|------|--------------------|--------------|
| Foreign-slug IDOR (admin) | Route-model binding by slug runs through the scoped Eloquent query; a foreign tenant's slug is not in scope → auto-404 | `admin cannot GET /products/{foreign-slug}` → 404 |
| Index cross-tenant leak | `TenantScope` adds `where products.tenant_id = ?` on every query | Seed 2 tenants; tenant A index returns only A's rows |
| Global validators leaking | `exists`/`unique` rules rewritten to `Rule::exists('categories','id')->where('tenant_id', $tid)` and `Rule::unique(...)->where('tenant_id', $tid)` in FormRequests (tid from `TenantContext::currentId()`) | `store product with foreign category_id` → 422 |
| Mass-assignment of `tenant_id` | `tenant_id` kept OUT of `$fillable`/`#[Fillable]`; set only by `creating` hook from context | `POST product with tenant_id=<other>` → row saved with token tenant, not the payload |
| Raw `DB::table` bypass | Convention: forbid raw `DB::table()`/`DB::raw` writes on tenant tables; `SlugGenerator` for products/categories MUST receive and filter by `tenant_id`; documented review checklist item | `SlugGenerator` collision test proves same slug allowed across tenants, blocked within tenant |
| `withTrashed` dropping tenant scope | `withTrashed()` only removes `SoftDeletingScope`; `TenantScope` is a separate global scope and stays applied | `admin A calls show withTrashed on B's trashed slug` → 404 (tenant scope still filters) |
| Octane context bleed | `TenantContext` bound `scoped()` (fresh per request) + `forgetCurrent()` on terminate | Simulated two sequential requests with different tenants assert no leakage of `current()` |
| Signup non-atomicity | Whole signup in one `DB::transaction`; duplicate-email caught (`QueryException`) → 422 | `register with existing email` → 422 and NO orphan tenant row created |
| Token valid after tenant soft-deleted | `ResolveTenant:admin` requires `status=active` + not soft-deleted → 401 | `soft-delete tenant, then call admin endpoint with its owner token` → 401 |
| Reserved-slug takeover | `RESERVED_SLUGS` blocklist in tenant slug generator | `register store_name="Admin"` → slug not `admin` (suffixed/rejected) |
| Ambiguous column on joins | `TenantScope` uses qualified `{table}.tenant_id` | `product index with category join` runs without ambiguous-column SQL error |
| Public-slug foreign access | `ResolveTenant:public` active-only lookup; inactive/missing → 404; scope filters products | `GET /public/{inactive-slug}/products` → 404; active tenant returns only its active products |

FormRequest scoping detail (the leak fix): in `StoreProductRequest::rules()` replace
`'category_id' => 'required|exists:categories,id'` with

```php
use Illuminate\Validation\Rule;

$tenantId = app(\App\Support\TenantContext::class)->currentId();

'category_id'   => ['required', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
'item_group_id' => ['nullable', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
```

(`Rule::exists` compiles to a raw builder query, so the tenant filter must be added
explicitly here — the global scope does not reach validator queries.)

---

## 6. Testing strategy (strict TDD)

Strict TDD is active (`composer test`, PHPUnit, SQLite `:memory:`, `RefreshDatabase`).
Every criterion below is a FAILING test written first, then made green. No production class
is written without a red test.

### 6.1 Test infrastructure

- **Tenant factory**: `TenantFactory` (status `active` default, `->suspended()`,
  `->trashed()` states).
- **Tenant-aware model factories**: `Product::factory()->for($tenant)` and
  `Category::factory()->for($tenant)` — factories set `tenant_id` explicitly (respected by
  the idempotent `creating` hook). A `Setting` factory likewise.
- **`TestCase` trait `InteractsWithTenancy`**:
  - `actingAsTenantOwner(Tenant $tenant): User` — creates an owner user for the tenant,
    `Sanctum::actingAs()` (or a real token), and asserts context is resolvable.
  - `withTenant(Tenant $tenant, callable $fn)` — sets `TenantContext` for direct-model
    isolation unit tests, forgets after.
- `RefreshDatabase` on every feature test (in-memory SQLite migrates fresh per test).

### 6.2 Test suite structure

```
tests/Feature/Tenancy/
  IndexIsolationTest.php        -> index no-leak (admin + public)
  ForeignSlugIdorTest.php       -> foreign-slug show 404
  CrossTenantWriteTest.php      -> create/update against foreign tenant blocked
  WithTrashedTenancyTest.php    -> withTrashed keeps tenant scope
  MassAssignmentTest.php        -> tenant_id payload ignored
  ScopedValidatorTest.php       -> foreign category_id/item_group_id 422
  SignupTest.php                -> atomic signup, reserved-slug 422/suffix, duplicate-email 422
  EnforceTenantMatchTest.php    -> token/URL slug mismatch 403 (when slug route present)
  CompositeSlugTest.php         -> same slug across tenants OK, within tenant blocked
  PublicCatalogTest.php         -> public active-tenant-only, inactive 404
tests/Unit/Tenancy/
  TenantScopeTest.php           -> apply() no-op when no context, qualified column
  TenantContextTest.php         -> scoped lifecycle, forgetCurrent, checkCurrent throws
```

### 6.3 Mapping to the proposal's 10 success criteria

| # | Success criterion | Test |
|---|-------------------|------|
| 1 | Index no-leak | `IndexIsolationTest` |
| 2 | Foreign-slug IDOR → 404 | `ForeignSlugIdorTest` |
| 3 | Cross-tenant write blocked | `CrossTenantWriteTest` |
| 4 | `withTrashed` no-leak | `WithTrashedTenancyTest` |
| 5 | Mass-assignment guarded | `MassAssignmentTest` |
| 6 | Cross-tenant `category_id` → 422 | `ScopedValidatorTest` |
| 7 | Signup atomic + reserved-slug 422 + duplicate-email 422 | `SignupTest` |
| 8 | Token/URL mismatch → 403 | `EnforceTenantMatchTest` |
| 9 | Composite slug uniqueness across tenants | `CompositeSlugTest` |
| 10 | Public catalog active-tenant-only | `PublicCatalogTest` |

---

## 7. Open items resolved

**Token valid after tenant soft-deleted** — RESOLVED for v1: `ResolveTenant:admin` loads the
token user's tenant with an active-and-not-soft-deleted filter. A soft-deleted (or future
suspended) tenant therefore fails resolution and the request returns `401`. This makes the
token functionally dead without needing eager token revocation. FULL token revocation
(iterating and deleting `personal_access_tokens` on tenant lifecycle change) is DEFERRED to
the suspend/deactivate cycle; v1 relies on resolution-time rejection, which is sufficient
because every admin route passes through `ResolveTenant`.

**Public-slug enumeration** — RESOLVED as accepted-inherent-with-mitigation: any public
catalog keyed by a human-readable slug is enumerable by nature (the slug IS the public
address). Mitigations that ship in v1: (1) active-only lookup so suspended/deleted tenants
return `404` and leak nothing, (2) no internal integer ids in any public response
(`ProductResource` already emits the slug as `id`), (3) a per-IP `public-catalog` read
throttle (60/min) to blunt bulk scraping. No CAPTCHA or signed-URL scheme in v1 — the data
is public-by-design (it is a storefront).

---

## Checklist for `sdd-apply`

- [ ] `tenants` migration + `TenantFactory`
- [ ] `tenant_id` migrations for products/categories/settings/users (+ `role`), drop global uniques, add composite partial uniques
- [ ] `App\Support\TenantContext` (bound `scoped()` in `AppServiceProvider`)
- [ ] `App\Models\Scopes\TenantScope` (qualified column, no-op, `withoutTenancy` macro)
- [ ] `App\Models\Concerns\BelongsToTenant` (add to Product, Category, Setting)
- [ ] `Tenant` model (soft-delete, active-scope helper)
- [ ] `ResolveTenant` (admin+public modes) + `EnforceTenantMatch`, aliases in `bootstrap/app.php`
- [ ] Signup: `RegisterController` + `RegisterRequest` + `TenantSignupService` + tenant slug generator (reserved blocklist)
- [ ] Scope FormRequest validators (`Rule::exists/unique ->where('tenant_id', ...)`)
- [ ] Public catalog controllers + read-only routes + throttles
- [ ] Full `tests/Feature/Tenancy` + `tests/Unit/Tenancy` suite (red-first)

## Next step

Proceed to `sdd-tasks` once the spec is also ready — this design plus the spec feed the task
breakdown.
