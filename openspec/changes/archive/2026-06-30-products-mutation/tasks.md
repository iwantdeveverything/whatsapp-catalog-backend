# Tasks: Products Mutation

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~300-350 lines |
| 400-line budget risk | Medium |
| Chained PRs recommended | No |
| Suggested split | Single PR |
| Delivery strategy | ask-on-risk |
| Chain strategy | stacked-to-main |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: stacked-to-main
400-line budget risk: Medium

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Full mutation implementation | PR 1 | Base branch: master; includes tests |

## Phase 1: Foundation (Policies & Requests)

- [x] 1.1 RED: Write `tests/Feature/Api/Products/ProductPolicyTest.php` for `create`, `update`, `delete`.
- [x] 1.2 GREEN: Add `create`, `update`, `delete` methods to `app/Policies/ProductPolicy.php` (returning `true`).
- [x] 1.3 RED: Write test stub for `UpdateProductRequest` validation logic.
- [x] 1.4 GREEN: Update `app/Http/Requests/StoreProductRequest.php` to use `$this->user()?->can('create', Product::class)` in `authorize()`.
- [x] 1.5 GREEN: Create `app/Http/Requests/UpdateProductRequest.php` with `sometimes` rules and policy authorization.

## Phase 2: Core Implementation (Store)

- [x] 2.1 RED: Write `tests/Feature/Api/Products/StoreTest.php` to assert product creation, auto-slug, and 201 response.
- [x] 2.2 GREEN: Update `ProductController@__construct` to inject `SlugGenerator` and `ProductController@store` to handle creation.

## Phase 3: Core Implementation (Update & Destroy)

- [x] 3.1 RED: Write `tests/Feature/Api/Products/UpdateTest.php` to assert partial updates, array replacement, and conditional slug regeneration.
- [x] 3.2 GREEN: Update `ProductController@update` to use `UpdateProductRequest` and save partial data.
- [x] 3.3 RED: Write `tests/Feature/Api/Products/DestroyTest.php` to assert soft-deletes, 204 response, and hiding from index.
- [x] 3.4 GREEN: Update `ProductController@destroy` to check authorization and perform `delete()`.

## Phase 4: Refactor & Verification

- [x] 4.1 REFACTOR: Run `php artisan test --filter Product` and clean up any redundant code or imports.
