<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Services\SlugGenerator;

class ProductController extends Controller
{
    public function __construct(private readonly SlugGenerator $slugGenerator) {}

    /**
     * List active, non-trashed products with optional search/category
     * filters and allow-listed sorting (PROD-01, PROD-02).
     */
    public function index(IndexProductRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::query()
            ->active()
            ->with('category')
            ->search($request->input('search'))
            ->when(
                $request->filled('category'),
                fn ($query) => $query->whereHas(
                    'category',
                    fn ($categoryQuery) => $categoryQuery->where('slug', $request->input('category'))
                )
            )
            ->ordered(
                $request->input('sortBy', 'name'),
                $request->input('sortOrder', 'asc')
            )
            ->get();

        return ProductResource::collection($products)->response();
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->slugGenerator->generate($data['name'], 'products');

        $product = Product::create($data);
        $product->load('category');

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(\Illuminate\Http\Response::HTTP_CREATED);
    }

    /**
     * Show a single product resolved by slug, including soft-deleted rows
     * (PROD-03). Trashed products serialize with `isActive=false`. The
     * `withTrashed` route binding (routes/api.php) supplies trashed rows.
     */
    public function show(Product $product): ProductResource
    {
        $this->authorize('view', $product);

        $product->load('category');

        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $data = $request->validated();

        if (array_key_exists('name', $data) && $data['name'] !== $product->name) {
            $data['slug'] = $this->slugGenerator->generate($data['name'], 'products', $product->id);
        }

        $product->update($data);
        $product->load('category');

        return new ProductResource($product);
    }

    public function destroy(Product $product): \Illuminate\Http\Response
    {
        $this->authorize('delete', $product);

        $product->delete();

        return response()->noContent();
    }
}
