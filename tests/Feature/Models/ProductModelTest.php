<?php

namespace Tests\Feature\Models;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_key_name_is_slug(): void
    {
        $this->assertSame('slug', (new Product)->getRouteKeyName());
    }

    public function test_soft_delete_hides_then_with_trashed_reveals(): void
    {
        $product = Product::factory()->create();
        $id = $product->id;

        $product->delete();

        $this->assertNull(Product::query()->find($id));
        $this->assertNotNull(Product::withTrashed()->find($id));
        $this->assertNotNull(Product::withTrashed()->find($id)->deleted_at);
    }

    public function test_is_active_casts_to_boolean(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $this->assertIsBool($product->fresh()->is_active);
        $this->assertTrue($product->fresh()->is_active);
    }
}
