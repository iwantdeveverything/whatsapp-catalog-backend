# Multitenancy Foundation — Specification

Observable behavior contract for backend row-level tenant isolation. Requirements describe WHAT must be true (HTTP status, DB state, isolation guarantees), not HOW. Every scenario is testable via a PHPUnit feature test. Scoped models = `Product`, `Category`, `Setting`.

## Capability: tenant-isolation

### Requirement: MT-ISO-001 Scoped index returns only current-tenant rows

A scoped model index query MUST return only rows owned by the active tenant.

#### Scenario: Index excludes other tenants
- GIVEN tenant A and tenant B each own products
- WHEN an authenticated tenant-A caller lists products
- THEN the response contains only tenant-A products
- AND no tenant-B product appears

### Requirement: MT-ISO-002 Foreign-slug binding returns 404

Route-model binding for a scoped model MUST resolve only within the active tenant; a foreign slug MUST return 404.

#### Scenario: Show/update/delete of foreign slug 404s
- GIVEN tenant B owns product with slug `pizza`
- WHEN a tenant-A caller requests show, update, or delete on slug `pizza`
- THEN the response is 404
- AND tenant-B's row is unchanged

### Requirement: MT-ISO-003 Cross-tenant write is blocked

A caller MUST NOT update or delete a scoped row owned by another tenant.

#### Scenario: Cross-tenant mutation blocked
- GIVEN tenant B owns a product
- WHEN a tenant-A caller attempts to update or delete it
- THEN the response is 404
- AND the tenant-B row is unmodified in the database

### Requirement: MT-ISO-004 withTrashed composes with tenant scope

`withTrashed()` MUST remove only the soft-delete constraint, never the tenant constraint.

#### Scenario: Trashed fetch stays within tenant
- GIVEN tenant A and tenant B each have soft-deleted products
- WHEN a tenant-A query includes `withTrashed()`
- THEN only tenant-A rows (including trashed) are returned
- AND no tenant-B trashed row appears

### Requirement: MT-ISO-005 tenant_id is never client-assignable

Request-body `tenant_id` MUST NOT override the context-derived tenant. On create, the active tenant MUST be auto-stamped.

#### Scenario: Mass-assignment ignored
- GIVEN an authenticated tenant-A caller
- WHEN they create a product with body `tenant_id` set to tenant B
- THEN the stored row's `tenant_id` equals tenant A

#### Scenario: Auto-fill on create
- GIVEN an active tenant context
- WHEN a scoped model is created without an explicit `tenant_id`
- THEN the persisted row carries the active tenant's id

### Requirement: MT-ISO-006 Deliberate cross-tenant escape hatch exists

A `withoutTenancy()` mechanism MUST allow deliberate cross-tenant queries for administrative/internal use. It MUST NOT be reachable through any API surface.

#### Scenario: Escape hatch bypasses scope
- GIVEN rows owned by multiple tenants
- WHEN a query uses `withoutTenancy()`
- THEN rows from all tenants are returned

## Capability: tenant-resolution

### Requirement: MT-RES-001 Admin tenant resolved from token

On the admin API, the tenant MUST be derived from the Sanctum token user's `tenant_id`, never from client header, body, or query.

#### Scenario: Token drives tenant
- GIVEN a token bound to tenant A
- WHEN the caller lists products
- THEN results are scoped to tenant A regardless of any header/body/query value

### Requirement: MT-RES-002 Admin slug mismatch returns 403

If an admin route carries a tenant slug, it MUST agree with the token tenant; a mismatch MUST return 403.

#### Scenario: Mismatched slug rejected
- GIVEN a token bound to tenant A
- WHEN the caller hits an admin route carrying tenant B's slug
- THEN the response is 403

### Requirement: MT-RES-003 Public tenant resolved from validated slug

On the public catalog, the tenant MUST be resolved from the URL slug; unknown, soft-deleted, or non-active tenant MUST return 404.

#### Scenario: Inactive/unknown slug 404s
- GIVEN a slug that is unknown, soft-deleted, or non-active
- WHEN a tokenless public catalog request is made
- THEN the response is 404

### Requirement: MT-RES-004 Public catalog is read-only, active-only

The public catalog MUST expose only active, non-trashed products of the resolved active tenant, and MUST reject writes.

#### Scenario: Only active products exposed
- GIVEN an active tenant with active and trashed products
- WHEN a public catalog list is requested
- THEN only active, non-trashed products of that tenant are returned

## Capability: tenant-signup

### Requirement: MT-SIGNUP-001 Signup is atomic

A valid signup MUST create a tenant (`status=active`), an owner user (`role=owner`, linked `tenant_id`), seed `Setting` defaults, and return a Sanctum token — all in one atomic transaction.

#### Scenario: Successful signup
- GIVEN a valid signup payload
- WHEN the signup endpoint is called
- THEN a tenant, owner user, and seeded settings exist
- AND a Sanctum token is returned

#### Scenario: Partial failure rolls back
- GIVEN a signup that fails mid-transaction
- WHEN the operation aborts
- THEN no tenant, user, or setting rows persist (zero orphans)

### Requirement: MT-SIGNUP-002 Invalid signup returns 422

Duplicate email MUST return 422 (not 500). A reserved slug (e.g. `admin`, `api`, `login`, `public`, `www`) MUST return 422.

#### Scenario: Duplicate email
- GIVEN an email already registered
- WHEN signup is attempted with that email
- THEN the response is 422

#### Scenario: Reserved slug
- GIVEN a signup whose derived slug is on the reserved blocklist
- WHEN signup is attempted
- THEN the response is 422

### Requirement: MT-SIGNUP-003 Slug collisions auto-resolve

Non-reserved slug collisions MUST be auto-resolved by a generator so signup succeeds with a distinct slug.

#### Scenario: Two identical names
- GIVEN a tenant with slug `acme` exists
- WHEN a second "Acme" signup occurs
- THEN it succeeds with a distinct slug and no error

### Requirement: MT-SIGNUP-004 Signup is rate-limited per IP

Signup MUST be throttled per IP (`throttle:signup`); exceeding the limit MUST return 429 with the same JSON envelope shape as `throttle:login`.

#### Scenario: Throttle exceeded
- GIVEN the per-IP signup limit is exceeded
- WHEN another signup is attempted
- THEN the response is 429 with the login-throttle JSON envelope shape

## Capability: tenant-authz

### Requirement: MT-AUTHZ-001 Login resolves tenant from user

Login by email MUST resolve the user (email globally unique) and derive the tenant from `user.tenant_id`. The issued token MUST be bound to that user's tenant.

#### Scenario: Login binds tenant
- GIVEN a user belonging to tenant A
- WHEN they log in by email and password
- THEN the issued token operates scoped to tenant A

### Requirement: MT-AUTHZ-002 Owner-only write policies

`ProductPolicy` and `CategoryPolicy` MUST gate create/update/delete to the tenant owner.

#### Scenario: Non-owner denied
- GIVEN a non-owner user in the active tenant
- WHEN they attempt create/update/delete
- THEN authorization is denied (403)

#### Scenario: Owner allowed
- GIVEN the tenant owner
- WHEN they perform create/update/delete on their own rows
- THEN the action is authorized

## Capability: tenant-uniqueness

### Requirement: MT-UNIQ-001 Composite slug uniqueness

Slugs on products and categories MUST be unique per `(tenant_id, slug)`: two tenants MAY share a slug; one tenant MUST NOT duplicate a slug.

#### Scenario: Same slug across tenants allowed
- GIVEN tenant A has product slug `pizza`
- WHEN tenant B creates a product with slug `pizza`
- THEN it succeeds

#### Scenario: Duplicate slug within tenant rejected
- GIVEN tenant A has product slug `pizza`
- WHEN tenant A creates another product with slug `pizza`
- THEN the response is 422

### Requirement: MT-UNIQ-002 Scoped reference validators

`StoreProductRequest` MUST validate `category_id` and `item_group_id` only against the current tenant's rows; a cross-tenant id MUST return 422.

#### Scenario: Cross-tenant category rejected
- GIVEN a category owned by tenant B
- WHEN a tenant-A caller creates a product referencing that `category_id`
- THEN the response is 422

## Success-criteria traceability

| Proposal criterion | Requirement |
|--------------------|-------------|
| Index never leaks | MT-ISO-001 |
| Foreign-slug IDOR 404 | MT-ISO-002 |
| Cross-tenant write blocked | MT-ISO-003 |
| withTrashed no-leak | MT-ISO-004 |
| Mass-assignment guarded | MT-ISO-005 |
| Cross-tenant category_id 422 | MT-UNIQ-002 |
| Signup atomic + reserved/dup 422 | MT-SIGNUP-001, MT-SIGNUP-002 |
| Token/URL mismatch 403 | MT-RES-002 |
| Composite slug uniqueness | MT-UNIQ-001 |
| Public active-tenant-only | MT-RES-003, MT-RES-004 |
