<?php

namespace Tests\Feature\Api\Categories;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryResourceShapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_emits_only_contract_keys(): void
    {
        $category = Category::factory()->create([
            'name' => 'Running',
            'slug' => 'running',
            'description' => 'Sport',
            'is_active' => true,
        ]);

        $category->loadCount('products');

        $array = (new CategoryResource($category))->toArray(request());

        $this->assertSame([
            'id' => 'running',
            'name' => 'Running',
            'slug' => 'running',
            'description' => 'Sport',
            'productCount' => 0,
        ], $array);

        $this->assertSame(
            ['id', 'name', 'slug', 'description', 'productCount'],
            array_keys($array)
        );
    }

    public function test_resource_id_equals_slug_and_product_count_reflects_relation(): void
    {
        $category = Category::factory()->create([
            'name' => 'Casual',
            'slug' => 'casual',
            'description' => null,
            'is_active' => false,
        ]);

        Product::factory()->count(2)->for($category)->create();

        $category->loadCount('products');

        $array = (new CategoryResource($category))->toArray(request());

        $this->assertSame('casual', $array['id']);
        $this->assertSame('casual', $array['slug']);
        $this->assertNull($array['description']);
        $this->assertArrayNotHasKey('isActive', $array);
        $this->assertSame(2, $array['productCount']);
    }
}
