<?php

namespace Tests\Feature\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_persists_product_with_valid_category_relation(): void
    {
        $product = Product::factory()->create();

        $this->assertTrue($product->exists);
        $this->assertNotEmpty($product->name);
        $this->assertNotNull($product->category_id);
        $this->assertInstanceOf(Category::class, $product->category);
    }

    public function test_factory_can_attach_to_existing_category(): void
    {
        $category = Category::factory()->create();

        $product = Product::factory()->for($category)->create();

        $this->assertSame($category->id, $product->category_id);
        $this->assertIsNumeric($product->price);
    }
}
