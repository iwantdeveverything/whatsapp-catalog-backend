<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
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

    public function store(StoreProductRequest $request)
    {
        $product = Product::create($request->validated());

        return response()->json($product, 201);
    }

    public function show(Product $product)
    {
        return $product->load(['category', 'variants']);
    }

    public function update(Request $request, Product $product)
    {
        // Usamos all() para simplificar el ejemplo,
        // en producción requeriría su UpdateProductRequest
        $product->update($request->all());

        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->noContent();
    }
}
