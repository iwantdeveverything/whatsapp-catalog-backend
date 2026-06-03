<?php

namespace Tests\Feature\Api\Products;

use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductResourceShapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_emits_only_contract_keys(): void
    {
        $category = Category::factory()->create(['name' => 'Running', 'slug' => 'running']);

        $product = Product::factory()->for($category)->create([
            'name' => 'Alpha',
            'slug' => 'alpha',
            'description' => 'Fast shoe',
            'price' => 19.99,
            'currency' => 'USD',
            'whatsapp' => '+5491100000000',
            'phone' => null,
            'is_active' => true,
            'images' => ['https://example.test/a.jpg'],
        ]);

        $product->load('category');

        $array = (new ProductResource($product))->toArray(request());

        $this->assertSame(
            ['id', 'name', 'description', 'price', 'currency', 'category', 'images', 'isActive', 'contact'],
            array_keys($array)
        );

        $this->assertSame('alpha', $array['id']);
        $this->assertSame('Alpha', $array['name']);
        $this->assertSame('Fast shoe', $array['description']);
        $this->assertSame(19.99, $array['price']);
        $this->assertSame('USD', $array['currency']);
        $this->assertSame('Running', $array['category']);
        $this->assertSame(['https://example.test/a.jpg'], $array['images']);
        $this->assertTrue($array['isActive']);
        $this->assertSame(['whatsapp' => '+5491100000000', 'phone' => null], $array['contact']);
    }

    public function test_price_is_a_float_not_a_string(): void
    {
        $product = Product::factory()->create(['price' => 30]);
        $product->load('category');

        $array = (new ProductResource($product))->toArray(request());

        $this->assertIsFloat($array['price']);
        $this->assertSame(30.0, $array['price']);
    }

    public function test_null_price_passes_through_as_null(): void
    {
        $product = Product::factory()->make(['price' => null]);

        $array = (new ProductResource($product))->toArray(request());

        $this->assertNull($array['price']);
    }

    public function test_is_active_false_when_flag_is_false(): void
    {
        $product = Product::factory()->create(['is_active' => false]);
        $product->load('category');

        $array = (new ProductResource($product))->toArray(request());

        $this->assertFalse($array['isActive']);
    }

    public function test_is_active_false_when_soft_deleted(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $product->delete();

        $trashed = Product::withTrashed()->find($product->id);
        $trashed->load('category');

        $array = (new ProductResource($trashed))->toArray(request());

        $this->assertFalse($array['isActive']);
    }

    public function test_no_internal_fields_leak(): void
    {
        $product = Product::factory()->create();
        $product->load('category');

        $array = (new ProductResource($product))->toArray(request());

        foreach (['category_id', 'deleted_at', 'created_at', 'updated_at', 'item_group_id', 'slug', 'variant_options'] as $leak) {
            $this->assertArrayNotHasKey($leak, $array);
        }
    }
}
