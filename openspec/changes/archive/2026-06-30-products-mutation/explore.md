## Exploration: Products Mutation (Create, Edit, Delete)

### Current State
- `routes/api.php` already defines `Route::apiResource('products', ProductController::class)->withTrashed(['show']);`, meaning routes exist but are only partially stubbed out in the controller.
- `ProductController` has `store`, `update`, and `destroy` methods, but they are mocked (e.g. `$request->all()`) and lack slug generation and proper resource responses.
- `StoreProductRequest` exists with good validation rules but its `authorize` method just returns `true` instead of delegating to a Policy.
- `UpdateProductRequest` does not exist.
- `ProductPolicy` is missing `create`, `update`, and `delete` methods.
- Soft deletes are supported (Product model uses `SoftDeletes`).

### Affected Areas
- `app/Http/Controllers/Api/ProductController.php` — Needs `SlugGenerator` injected, proper `$request->validated()` usage, and consistent JSON Resource returns.
- `app/Http/Requests/StoreProductRequest.php` — Needs authorization check pointing to `ProductPolicy`.
- `app/Http/Requests/UpdateProductRequest.php` — Needs to be created for partial updates (`sometimes` rules).
- `app/Policies/ProductPolicy.php` — Needs `create`, `update`, and `delete` methods (returning `true` for any authenticated user, mirroring `CategoryPolicy`).

### Approaches
1. **Standard Laravel Resource Approach (Recommended)** — Align completely with the patterns established in `CategoryController`. Use `ProductPolicy` for permissions, dedicated FormRequests for validation/authorization, and `SlugGenerator` to automatically build slugs from product names.
   - Pros: Maximum consistency across the codebase, robust authorization, dry controllers.
   - Cons: None.
   - Effort: Low

### Recommendation
Use the Standard Laravel Resource Approach. It maintains strict architectural parity with the existing `CategoryController` and leverages the existing `SlugGenerator`.

### Risks
- Unique constraints on slugs: we need to ensure the `SlugGenerator` gets the correct context (`'products'`) to avoid collisions.
- Partial updates: `UpdateProductRequest` must properly handle optional fields (using `sometimes` or identical rules without `required`).

### Ready for Proposal
Yes. The path forward is crystal clear and matches existing patterns perfectly.
