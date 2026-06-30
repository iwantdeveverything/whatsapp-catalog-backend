<?php

namespace Tests\Feature\Api\Products;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_soft_deletes_a_product_and_returns_204(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->create();

        $response = $this->deleteJson('/api/products/' . $product->slug);

        $response->assertStatus(204);

        $this->assertSoftDeleted('products', [
            'id' => $product->id,
        ]);
    }

    public function test_it_fails_authorization_for_unauthenticated_users(): void
    {
        $product = Product::factory()->create();

        $response = $this->deleteJson('/api/products/' . $product->slug);

        $response->assertStatus(401);
    }
}
