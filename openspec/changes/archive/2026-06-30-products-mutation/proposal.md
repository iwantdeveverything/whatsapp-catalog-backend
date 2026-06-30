## Proposal: Products Mutation

### Business Problem
We currently have read-only endpoints for products (`index` and `show`). To give administrators full control over the catalog, we need to implement the mutation endpoints (`store`, `update`, `destroy`) in the `ProductController`.

### Goals
- Enable creation of products with auto-generated, collision-free slugs.
- Enable full, flexible partial updates of any product attribute.
- Enable soft-deletion of products so that existing orders or cached views in the frontend gracefully handle them (e.g. marking them as "unavailable") instead of returning 404s.
- Maintain strict architectural consistency with the existing Category implementation.

### Non-Goals
- Role-based Access Control (RBAC): We will stub policies to return `true` for any authenticated user for now, leaving actual admin-role checks for a future phase.

### Proposed Solution
1. **Store Endpoint**:
   - Route: `POST /api/products`
   - Request: `StoreProductRequest` (with authorization delegated to `ProductPolicy`).
   - Logic: Validates input, generates a unique slug via `SlugGenerator`, saves the product, and returns a 201 `ProductResource`.

2. **Update Endpoint**:
   - Route: `PUT/PATCH /api/products/{product}`
   - Request: `UpdateProductRequest` (new FormRequest using `sometimes` rules for partial updates).
   - Logic: Accepts full replacement of JSON arrays like `images` to allow individual URL modifications from the frontend's perspective. If `name` is modified, regenerate the slug using `SlugGenerator`. Returns the updated `ProductResource`.

3. **Destroy Endpoint**:
   - Route: `DELETE /api/products/{product}`
   - Logic: Checks `ProductPolicy@delete`. Executes a soft-delete on the model. Returns `204 No Content`.

### Business Rules & Constraints
1. **Total Flexibility**: Any field on the product can be modified after creation. There are no immutable fields.
2. **Image Array Handling**: The `images` array is completely flexible. The frontend will send the desired final array of URLs (allowing addition, removal, or reordering of individual URLs seamlessly).
3. **Soft Deletes**: Deleting a product does not physically remove it. It is soft-deleted so the frontend can gracefully display a "Not Available" state if a user holds a cached reference.

### Testing Strategy
- **Unit/Feature Tests**: Create test cases in `tests/Feature/Api/Products/` for `StoreTest.php`, `UpdateTest.php`, and `DestroyTest.php`.
- Validate that slugs are auto-generated on creation and regenerated only if the name changes on update.
- Ensure validation rules accurately reject invalid input.
- Assert soft-deletes correctly hide products from the index but keep them available via the `show` endpoint which uses the `withTrashed` route binding.
