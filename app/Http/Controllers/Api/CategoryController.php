<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\SlugGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function __construct(private readonly SlugGenerator $slugGenerator) {}

    /**
     * List non-trashed categories with their non-trashed product counts (CAT-01).
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::query()
            ->withCount('products')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories)->response();
    }

    /**
     * Create a category with an auto-generated, collision-suffixed slug (CAT-04).
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->slugGenerator->generate($data['name'], 'categories');

        // `is_active` is not set here: the categories table has a DB-level
        // default of `true` (migration CAT-07), so new rows are active by
        // default. The client cannot set it (not in CategoryFormSchema).
        $category = Category::create($data);
        $category->loadCount('products');

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a single category resolved by slug (CAT-02).
     */
    public function show(Category $category): CategoryResource
    {
        $this->authorize('view', $category);

        $category->loadCount('products');

        return new CategoryResource($category);
    }

    /**
     * Partially update a category, regenerating the slug when the name changes (CAT-05).
     */
    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $data = $request->validated();

        if (array_key_exists('name', $data) && $data['name'] !== $category->name) {
            $data['slug'] = $this->slugGenerator->generate($data['name'], 'categories', $category->id);
        }

        $category->update($data);
        $category->loadCount('products');

        return new CategoryResource($category);
    }

    /**
     * Soft-delete a category unless it has non-trashed products attached (CAT-06).
     */
    public function destroy(Category $category): Response|JsonResponse
    {
        $this->authorize('delete', $category);

        if ($category->products()->exists()) {
            return response()->json(
                ['error' => 'Cannot delete category with existing products'],
                Response::HTTP_CONFLICT
            );
        }

        $category->delete();

        return response()->noContent();
    }
}
