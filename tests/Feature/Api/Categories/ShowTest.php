<?php

namespace Tests\Feature\Api\Categories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_resolves_category_by_slug_with_product_count(): void
    {
        $this->actingAsAdmin();

        $running = Category::factory()->create(['name' => 'Running', 'slug' => 'running']);
        Product::factory()->count(2)->for($running)->create();

        $response = $this->getJson('/api/categories/running');

        $response->assertStatus(200)
            ->assertJsonPath('id', 'running')
            ->assertJsonPath('slug', 'running')
            ->assertJsonPath('productCount', 2);

        $this->assertSame(
            ['id', 'name', 'slug', 'description', 'isActive', 'productCount'],
            array_keys($response->json())
        );
    }

    public function test_show_returns_404_for_unknown_slug(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/categories/nonexistent');

        $response->assertStatus(404);
    }

    public function test_show_requires_authentication(): void
    {
        Category::factory()->create(['slug' => 'running']);

        $response = $this->getJson('/api/categories/running');

        $response->assertStatus(401);
    }
}
