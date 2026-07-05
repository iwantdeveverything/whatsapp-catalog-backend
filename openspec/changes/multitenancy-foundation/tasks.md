# Tasks: Multitenancy Foundation (Backend)

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~1600–1900 (5 migrations + Tenant model + TenantContext + TenantScope + BelongsToTenant + 2 middleware + signup stack + slug generator + scoped validators + policies + public controllers + ~12 test files) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR1 Schema → PR2 Scope engine → PR3 Admin resolution → PR4 Signup → PR5 Uniqueness+validators/authz → PR6 Public catalog |
| Delivery strategy | ask-on-risk (default; none passed) |
| Chain strategy | pending (team decision — ask before apply) |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Est. lines | Notes |
|------|------|-----------|-----------|-------|
| 1 | Schema foundation | PR 1 | ~250 | Independent; migrations + Tenant model + factory |
| 2 | Scope engine + isolation | PR 2 | ~450 | Depends PR1; core invariant |
| 3 | Admin resolution + IDOR | PR 3 | ~300 | Depends PR2 |
| 4 | Atomic signup | PR 4 | ~320 | Depends PR2 (context/settings) |
| 5 | Uniqueness + validator/authz retrofit | PR 5 | ~280 | Depends PR2/PR3 |
| 6 | Public catalog | PR 6 | ~200 | Depends PR2/PR3 |

Chain-strategy note: slices are independently shippable → `stacked-to-main` fits speed; use `feature-branch-chain` if the tenancy cut must integrate atomically before main. PR2 is the only slice that risks >400 lines — split its tests from impl commits if it exceeds budget.

---

## Slice 1 / PR 1 — Schema foundation (MT-UNIQ-001 DB layer, MT-ISO base)

- [x] 1.1 test(db): `tests/Feature/Tenancy/TenantsSchemaTest` asserts `tenants` table (name, slug unique, status default active, softDeletes, status index) exists — RED
- [x] 1.2 feat(db): `2026_07_05_000001_create_tenants_table.php` migration to green
- [x] 1.3 test(db): assert `tenant_id` FK + index on products/categories/settings and `tenant_id` nullable + `role` default owner on users — RED (`TenantColumnsSchemaTest`)
- [x] 1.4 feat(db): 4 migrations `add_tenant_id_to_{products,categories,settings,users}` — `foreignId->constrained->restrictOnDelete`, drop global slug/key uniques, add partial composite `(tenant_id,slug) WHERE deleted_at IS NULL` on products/categories (raw DB::statement) + plain composite `(tenant_id,key)` on settings (no soft-deletes)
- [x] 1.5 feat(api): `App\Models\Tenant` (softDeletes, `active()` scope) + `TenantFactory` (default active, `->suspended()`, `->trashed()`, unique slug)
- [x] 1.6 refactor(api): Pint; committed as 2 work units (tenants foundation; tenant_id + composite uniqueness)
- [x] CAVEAT-A (RESOLVED): partial index `WHERE deleted_at IS NULL` verified working on SQLite :memory: test driver (composite storage tests green); prod PostgreSQL also supports it per engram #751. Implemented via raw `DB::statement('CREATE UNIQUE INDEX ...')`.
- DEVIATION (see engram #752): `tenant_id` is NULLABLE this slice (design §3.2 said NOT NULL). Auto-fill creating hook is slice 2; controllers create rows without tenant_id, so NOT NULL now would break write tests. Slice 2 must add the hook AND a tighten-to-NOT-NULL migration.
- Closes: DB substrate for MT-UNIQ-001 (storage half), MT-ISO-*.

## Slice 2 / PR 2 — Scope engine + isolation (MT-ISO-001..006)

- [ ] 2.1 test(api): `tests/Unit/Tenancy/TenantContextTest` — set/current/currentId/hasTenant/forgetCurrent, `checkCurrent` throws `TenantContextMissingException` — RED
- [ ] 2.2 feat(api): `App\Support\TenantContext` + exception; bind `scoped()` in `AppServiceProvider::register()`
- [ ] 2.3 test(api): `tests/Unit/Tenancy/TenantScopeTest` — no-op without tenant, qualified `{table}.tenant_id`, `withoutTenancy()` macro (MT-ISO-006) — RED
- [ ] 2.4 feat(api): `App\Models\Scopes\TenantScope` + `App\Models\Concerns\BelongsToTenant` (addGlobalScope, idempotent creating hook, tenant() relation, tenant_id OUT of fillable)
- [ ] 2.5 feat(api): apply `BelongsToTenant` to Product/Category/Setting; add tenant-aware factory states (`->for($tenant)`); add `InteractsWithTenancy` TestCase trait
- [ ] 2.6 test(api): `IndexIsolationTest` (MT-ISO-001), `MassAssignmentTest` (MT-ISO-005 both scenarios), `WithTrashedTenancyTest` (MT-ISO-004) — RED → green already covered by 2.4/2.5
- [ ] 2.7 refactor(api): Pint; commit
- Test files: TenantContextTest, TenantScopeTest, IndexIsolationTest, MassAssignmentTest, WithTrashedTenancyTest. Closes criteria 1,4,5.
- SIZE WATCH: likely >400 lines — keep impl and test commits separable for possible sub-split.

## Slice 3 / PR 3 — Admin resolution + IDOR (MT-RES-001,002; MT-ISO-002,003; MT-AUTHZ-001)

- [ ] 3.1 test(api): `ForeignSlugIdorTest` (MT-ISO-002) foreign slug show/update/delete → 404, row unchanged — RED
- [ ] 3.2 test(api): `CrossTenantWriteTest` (MT-ISO-003) update/delete foreign row → 404, DB unmodified — RED
- [ ] 3.3 feat(api): `ResolveTenant` (admin mode `fromToken` active-only else 401; public mode stub `fromSlug`); alias `resolve.tenant` in `bootstrap/app.php`; wire admin route group `['auth:sanctum','resolve.tenant:admin']`; `terminate` forgetCurrent
- [ ] 3.4 test(api): `EnforceTenantMatchTest` (MT-RES-002) URL slug != token slug → 403 — RED
- [ ] 3.5 feat(api): `EnforceTenantMatch` + alias `tenant.match`; assert login binds token to `user.tenant_id` (MT-AUTHZ-001)
- [ ] 3.6 refactor(api): Pint; commit
- [ ] CAVEAT-C (flag): token revocation deferred — confirm EVERY admin route passes through `resolve.tenant:admin` (soft-deleted tenant → 401). Add route-audit checklist item.
- [ ] CAVEAT-D (flag): `EnforceTenantMatch` is UNEXERCISED by v1 CRUD (no admin route carries `{tenant}`). Its test is a guard for a future slug-carrying admin route — keep test present, do not delete.
- Test files: ForeignSlugIdorTest, CrossTenantWriteTest, EnforceTenantMatchTest. Closes criteria 2,3,8.

## Slice 4 / PR 4 — Atomic signup (MT-SIGNUP-001..004)

- [ ] 4.1 test(api): `SignupTest` — valid signup creates tenant+owner+seeded settings+token (MT-SIGNUP-001); partial failure rolls back, zero orphans — RED
- [ ] 4.2 feat(api): `RegisterRequest` (global-unique email) + `TenantSignupService` (`DB::transaction`: tenant+owner+seed settings+token, role/tenant_id set outside fillable) + `RegisterController::store` (QueryException→422, 201 mirror login)
- [ ] 4.3 test(api): duplicate email → 422, reserved slug (admin/api/login/public/www) → 422 (MT-SIGNUP-002); two "Acme" → distinct slug (MT-SIGNUP-003) — RED
- [ ] 4.4 feat(api): tenant slug generator `SlugGenerator::forTenant` (Str::slug + RESERVED_SLUGS blocklist + collision suffix vs `DB::table('tenants')`)
- [ ] 4.5 test(api): throttle:signup exceeded → 429 with login-throttle envelope (MT-SIGNUP-004) — RED
- [ ] 4.6 feat(api): `signup` RateLimiter in `configureRateLimiting()`; wire `throttle:signup` on register route
- [ ] 4.7 refactor(api): Pint; commit
- Test file: SignupTest. Closes criterion 7.

## Slice 5 / PR 5 — Uniqueness + validator/authz retrofit (MT-UNIQ-001,002; MT-AUTHZ-002)

- [ ] 5.1 test(api): `CompositeSlugTest` (MT-UNIQ-001) same slug across tenants OK, duplicate within tenant → 422 — RED
- [ ] 5.2 test(api): `ScopedValidatorTest` (MT-UNIQ-002) foreign category_id/item_group_id → 422 — RED
- [ ] 5.3 feat(api): rewrite `StoreProductRequest` (and update variants) `exists`/`unique` → `Rule::exists/unique(...)->where('tenant_id', currentId())`
- [ ] 5.4 test(api): `ProductPolicy`/`CategoryPolicy` — non-owner create/update/delete → 403, owner allowed (MT-AUTHZ-002) — RED
- [ ] 5.5 feat(api): owner-only policies + register in gates
- [ ] 5.6 refactor(api): Pint; commit
- [ ] CAVEAT-B (checklist): raw `DB::table`/`DB::raw` bypasses scope — add review-checklist item forbidding raw writes on tenant tables; SlugGenerator collision must filter by `tenant_id`.
- Test files: CompositeSlugTest, ScopedValidatorTest, policy tests. Closes criteria 6,9.

## Slice 6 / PR 6 — Public catalog (MT-RES-003,004)

- [ ] 6.1 test(api): `PublicCatalogTest` — inactive/unknown/soft-deleted slug → 404 (MT-RES-003); active tenant exposes only active+non-trashed products, writes rejected (MT-RES-004) — RED
- [ ] 6.2 feat(api): complete `ResolveTenant::fromSlug` (public mode, `withoutTenancy` active-only, 404); `public-catalog` RateLimiter
- [ ] 6.3 feat(api): `PublicProductController` + `PublicCategoryController` (read-only); public route group `prefix('public/{tenant}')->middleware(['resolve.tenant:public','throttle:public-catalog'])` GET-only
- [ ] 6.4 refactor(api): Pint; run full `composer test`; commit
- Test file: PublicCatalogTest. Closes criterion 10.

---

## Apply-time flags summary
- CAVEAT-A: verify partial-index driver support (Slice 1) — BLOCKING before migration finalize.
- CAVEAT-B: raw DB::table convention-only — review-checklist item (Slice 5).
- CAVEAT-C: token revocation deferred — audit all admin routes pass ResolveTenant (Slice 3).
- CAVEAT-D: EnforceTenantMatch unexercised by v1 CRUD — keep guard test (Slice 3).
