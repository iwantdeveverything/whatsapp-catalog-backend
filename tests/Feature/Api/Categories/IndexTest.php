<?php

namespace Tests\Feature\Api\Categories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_categories_with_product_count_excluding_soft_deleted_products(): void
    {
        $this->actingAsAdmin();

        $running = Category::factory()->create(['name' => 'Running', 'slug' => 'running']);
        $casual = Category::factory()->create(['name' => 'Casual', 'slug' => 'casual']);

        Product::factory()->count(3)->for($running)->create();
        Product::factory()->for($running)->create()->delete();

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200);

        $byId = collect($response->json())->keyBy('id');

        $this->assertSame(3, $byId['running']['productCount']);
        $this->assertSame(0, $byId['casual']['productCount']);
    }

    public function test_index_excludes_soft_deleted_categories(): void
    {
        $this->actingAsAdmin();

        Category::factory()->create(['slug' => 'visible']);
        Category::factory()->create(['slug' => 'trashed'])->delete();

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200);

        $slugs = collect($response->json())->pluck('slug')->all();

        $this->assertContains('visible', $slugs);
        $this->assertNotContains('trashed', $slugs);
    }

    public function test_index_returns_flat_array_of_contract_shaped_objects(): void
    {
        $this->actingAsAdmin();

        Category::factory()->create(['slug' => 'running']);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200);

        $first = $response->json()[0];

        $this->assertSame(
            ['id', 'name', 'slug', 'description', 'productCount'],
            array_keys($first)
        );
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/categories');

        $response->assertStatus(401);
    }
}
