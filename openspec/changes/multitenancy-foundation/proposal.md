# Multitenancy Foundation (Backend)

Convert the single-store catalog backend into a multi-tenant SaaS foundation where hundreds to thousands of merchants can self-signup and each operate an isolated store on shared infrastructure. This cycle delivers the backend isolation guarantee only: a row-level `tenant_id` model plus a hand-rolled global query scope so no catalog query can accidentally cross tenant boundaries. Frontend `[tenant]` routing is a later cycle.

## Intent

We want to bring professional-grade robustness to the catalog by making it a real multi-tenant SaaS: self-signup for many merchants (100s–1000s), each with a private product catalog, categories, and settings, served from one shared database and schema. Today the backend is single-tenant — one implicit store, no notion of "who owns this row." The goal of this cycle is to make tenant ownership a first-class, non-bypassable property of the data layer so that adding merchants is safe by construction, not by developer discipline.

## Problem

The current backend assumes exactly one store. Concretely:

- **No ownership dimension.** `Product`, `Category`, and `Setting` rows have no `tenant_id`. Every query returns every row.
- **Tenant-unaware queries exist today.** `AuthController.login` looks up users by email alone, `ProductController.index` and `CategoryController.index` return all rows, and `StoreProductRequest` validates `category_id` / `item_group_id` against the whole table.
- **Global uniqueness is wrong at scale.** Product and category slugs are globally unique, so two merchants can never both have a `pizza` product.
- **Stub authorization.** `ProductPolicy` / `CategoryPolicy` return true for everything; there is no owner gate.
- **No signup path.** There is no way to create a new store; the register endpoint does not yet exist.

At multi-merchant scale the dominant failure mode is a **cross-tenant data leak**: one forgotten `WHERE tenant_id = ?` exposes or mutates another merchant's catalog. The isolation guarantee we need is that a catalog query *cannot* omit the tenant filter, even when a developer forgets it.

## Approach

Adopt **shared database, shared schema, row-level `tenant_id`** isolation, enforced by a **hand-rolled Laravel global query scope** that auto-injects the tenant filter on every scoped model. The global scope is the source of truth: forgetting the filter is impossible because the ORM adds it. This is the only model that scales cheaply to thousands of self-signup tenants (no per-tenant database or schema provisioning).

### The resolution crux: two paths, one scope

The single global scope reads the active tenant from a request-scoped `TenantContext`. That context is populated by two mutually exclusive resolution paths, and **the tenant is never taken from a client-controlled header, body, or query parameter**:

| Surface | How tenant is resolved | Guard |
|---------|------------------------|-------|
| Admin API (`/api/...`) | From the Sanctum token → `user.tenant_id` (authoritative). No slug in the URL. | If an admin route *does* carry a slug, it may only agree; mismatch → **403** (closes IDOR). |
| Public catalog (`/api/public/{tenant}/...`) | From a validated active-tenant URL slug lookup only. Tokenless, read-only. | Exposes only an active tenant and its active, non-trashed products. |
| Non-request (jobs / console / seeders) | Scope is **off by design**. No jobs exist today — documented deferral. | N/A this cycle. |

### Why hand-rolled, not a package

The tenant-scoped surface is tiny: three models, an admin-only write API, one read-only public path, and no queue workers. A full package (stancl/tenancy, spatie/multitenancy) brings database-switching, domain routing, and job middleware we do not need, plus a learning and maintenance cost that outweighs ~6 small, auditable classes. Hand-rolling keeps the isolation logic explicit and testable.

### Planned build (high level — details belong to the design phase)

Roughly six small classes plus test scaffolding: a `BelongsToTenant` trait (auto-fills `tenant_id`, keeps it out of `$fillable`), a `TenantScope` global scope (qualified column, no-op when no active tenant, `withoutTenancy()` escape hatch), an Octane-safe `TenantContext`, `ResolveTenant` + `EnforceTenantMatch` middleware, an atomic transactional signup, scoped `FormRequest` validators, composite `(tenant_id, slug)` uniqueness, tenant-aware factories, and a full cross-tenant isolation feature-test suite. Class-level design is deferred to the design phase.

## Scope (this cycle)

- Add `tenant_id` (NOT NULL) to `Product`, `Category`, `Setting` — baked into birth migrations, fresh start, **no backfill**.
- `tenants` table with `status` and soft-delete; `users.tenant_id`.
- Hand-rolled global scope + `BelongsToTenant` trait + `TenantContext`.
- `ResolveTenant` and `EnforceTenantMatch` middleware for both API surfaces.
- Atomic self-signup (create tenant + owner user + seed `Setting` defaults + issue token), with reserved-slug blocklist and IP throttle.
- Scope existing tenant-unaware queries and validators (login, product/category index, `StoreProductRequest`).
- Owner-only policies replacing the all-true stubs.
- Composite `(tenant_id, slug)` uniqueness on products and categories; drop the global slug unique.
- Public read-only catalog path resolving tenant by validated active slug.
- Full cross-tenant isolation feature-test suite (strict TDD).

## Non-goals (deferred)

- Frontend Next.js `[tenant]` routing — later cycle.
- User model tenant scoping — `User` stays unscoped (login predates tenant context; user tenancy is enforced in middleware + signup instead).
- Email verification and richer anti-spam beyond IP throttle + reserved-slug blocklist.
- Staff and multi-role authorization — v1 is **owner only**.
- Tenant suspend / deactivate / hard-purge and a platform super-admin actor — v1 lifecycle is **soft-delete tenant only**.
- Queue/job tenant context — no jobs exist today.

## Key decisions

| Decision | v1 choice | Rationale |
|----------|-----------|-----------|
| Isolation model | Shared DB + shared schema + row-level `tenant_id` + global scope | Scales cheaply to 1000s of self-signup tenants. |
| Scoped models | `Product`, `Category`, `Setting` (`tenant_id` NOT NULL) | The catalog surface. `User` stays unscoped. |
| Email uniqueness | **Globally unique** | One human = one store = one account; login by email alone → `user.tenant_id`. |
| Activation | **Instant live** (`status = active` on signup) | Fastest path to value; anti-spam via IP throttle + reserved-slug blocklist. |
| Roles | **Owner only** | Policies gate to owner; staff/roles deferred. |
| Lifecycle | **Soft-delete tenant only** | Suspend/purge/super-admin deferred. |
| API transport | **Split**: admin (tenant from token) vs public (tenant from validated slug) | Client never sets tenant; two resolution paths, one scope. |
| Existing data | **Fresh start, no backfill** | Schema born multitenant; demo data discarded. |

## Risks & unknowns

Mitigations are for the design phase; this proposal names the risk classes.

- **Isolation-leak risk class (primary).** Any query that omits the tenant filter leaks or mutates another tenant's data. Vectors: foreign-slug route-model-binding IDOR, global validators (`exists` / `unique`) leaking cross-tenant, mass-assignment of `tenant_id`, raw `DB::table` bypass, and `withTrashed()` accidentally dropping the tenant scope alongside the soft-delete scope.
- **Context bleed.** Worker/Octane request reuse could carry a stale tenant into the next request if `TenantContext` is not cleared on terminate.
- **Signup non-atomicity.** A partial signup could leave an orphan tenant, a tenant-less user, or throw a 500 on duplicate email instead of a clean 422.
- **Token valid after tenant soft-deleted.** A previously issued token may still authenticate against a soft-deleted tenant. Open item for design.
- **Public-slug enumeration.** The public catalog path exposes tenant existence by slug; reserved-slug takeover is a related concern. Open item for design.

## Success criteria

Strict TDD: **every guarantee below has a failing test written first**, then made green. The cycle is done when the cross-tenant isolation suite is green and covers:

- [ ] Index queries never leak: a scoped model index returns only the caller's rows.
- [ ] Foreign-slug IDOR returns **404** (route-model binding cannot fetch another tenant's record).
- [ ] Cross-tenant write is blocked (cannot update/delete another tenant's row).
- [ ] `withTrashed()` composes with tenant scope — no cross-tenant leak when fetching soft-deleted rows.
- [ ] Mass-assignment of `tenant_id` is guarded (client cannot override ownership).
- [ ] Cross-tenant `category_id` on product create returns **422** (scoped validator).
- [ ] Signup is atomic: reserved slug → **422**, duplicate email → **422** (not 500), and partial failure creates no orphan records.
- [ ] Token / URL slug mismatch on an admin route returns **403**.
- [ ] Composite `(tenant_id, slug)` uniqueness holds: two tenants can share a slug; one tenant cannot duplicate it.
- [ ] Public catalog resolves an active tenant only and exposes only active, non-trashed products.

## Next step

Proceed to `sdd-spec` (requirements and scenarios) and `sdd-design` (class-level architecture and risk mitigations) — these can run in parallel from this proposal.
