<?php

namespace Tests\Feature\Api\Products;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_resolves_active_product_by_slug(): void
    {
        $this->actingAsAdmin();

        Product::factory()->create(['slug' => 'zapatillas-running', 'is_active' => true]);

        $response = $this->getJson('/api/products/zapatillas-running');

        $response->assertStatus(200)
            ->assertJsonPath('id', 'zapatillas-running')
            ->assertJsonPath('isActive', true);

        $this->assertSame(
            ['id', 'name', 'description', 'price', 'currency', 'category', 'images', 'isActive', 'contact'],
            array_keys($response->json())
        );
    }

    public function test_show_resolves_soft_deleted_product_with_inactive_flag(): void
    {
        $this->actingAsAdmin();

        Product::factory()->create(['slug' => 'descontinuado', 'is_active' => true])->delete();

        $response = $this->getJson('/api/products/descontinuado');

        $response->assertStatus(200)
            ->assertJsonPath('id', 'descontinuado')
            ->assertJsonPath('isActive', false);
    }

    public function test_show_returns_404_for_unknown_slug(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/products/nonexistent');

        $response->assertStatus(404);
    }

    public function test_show_requires_authentication(): void
    {
        Product::factory()->create(['slug' => 'zapatillas-running']);

        $response = $this->getJson('/api/products/zapatillas-running');

        $response->assertStatus(401);
    }
}
