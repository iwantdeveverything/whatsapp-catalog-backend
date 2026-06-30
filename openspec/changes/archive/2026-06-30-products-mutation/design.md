## Design: Products Mutation

### Architecture Overview
The products mutation feature adheres to the existing Hexagonal-lite/Clean architecture of the Laravel application. It leverages FormRequests for validation and Policies for authorization, keeping the `ProductController` slim and strictly focused on HTTP transport and standard CRUD orchestration.

### Component Details

#### 1. `ProductController`
- **Dependency Injection**: Inject `SlugGenerator` in the constructor.
- **`store` method**:
  - Accept `StoreProductRequest`.
  - Extract validated data.
  - Generate `slug` using `$this->slugGenerator->generate($data['name'], 'products')`.
  - Call `Product::create($data)`.
  - Return `(new ProductResource($product))->response()->setStatusCode(201)`.
- **`update` method**:
  - Accept `UpdateProductRequest` and `Product $product`.
  - Extract validated data.
  - If `name` is present in data and differs from `$product->name`, regenerate `slug` using `$this->slugGenerator->generate($data['name'], 'products', $product->id)`.
  - Call `$product->update($data)`.
  - Return `new ProductResource($product)`.
- **`destroy` method**:
  - Accept `Product $product`.
  - Check `$this->authorize('delete', $product)`. (Note: Laravel's implicit routing automatically handles 404s, so if we rely on soft-deletes, `destroy` will only find non-trashed products, which is correct).
  - Call `$product->delete()`.
  - Return `response()->noContent()`.

#### 2. `StoreProductRequest`
- **`authorize()`**: Change to `return $this->user()?->can('create', Product::class) ?? false;`
- **`rules()`**: Ensure all required rules are present. (Already exists and looks solid).

#### 3. `UpdateProductRequest` (New)
- **`authorize()`**: `return $this->user()?->can('update', $this->route('product')) ?? false;`
- **`rules()`**: Similar to `StoreProductRequest`, but practically all fields except ID/slug should use the `sometimes` rule to allow partial updates.

#### 4. `ProductPolicy`
- **New Methods**:
  - `public function create(User $user): bool { return true; }`
  - `public function update(User $user, Product $product): bool { return true; }`
  - `public function delete(User $user, Product $product): bool { return true; }`

### Data Flow for Partial Update
1. Client sends `PATCH /api/products/{id}` with `{"price": 100, "images": ["url1"]}`.
2. `UpdateProductRequest` validates `price` and `images`.
3. `ProductController` checks if `name` is provided (it's not).
4. `Product::update` is called with the subset of data. The JSON column `images` is wholly overwritten with the new array.
5. Soft-deleted relations (if any) are untouched.

### Risk Mitigations
- **Slug Collisions**: Reusing the `SlugGenerator` ensures the `slug` generation includes collision checks against the `products` table.
- **Images Array Replacement**: Relying on eloquent JSON column casting for `images` means the entire array is overwritten, fulfilling the "total flexibility" requirement without custom array diffing logic.
