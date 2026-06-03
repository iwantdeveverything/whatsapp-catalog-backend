<?php

namespace Tests\Feature\Api\Products;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_active_non_trashed_products(): void
    {
        $this->actingAsAdmin();

        $active = Product::factory()->create(['slug' => 'active', 'is_active' => true]);
        Product::factory()->create(['slug' => 'inactive', 'is_active' => false]);
        Product::factory()->create(['slug' => 'trashed', 'is_active' => true])->delete();

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);

        $ids = collect($response->json())->pluck('id')->all();

        $this->assertSame(['active'], $ids);
    }

    public function test_index_returns_empty_array_when_no_products(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);
        $this->assertSame([], $response->json());
    }

    public function test_index_returns_contract_shaped_objects(): void
    {
        $this->actingAsAdmin();

        Product::factory()->create(['is_active' => true]);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);

        $this->assertSame(
            ['id', 'name', 'description', 'price', 'currency', 'category', 'images', 'isActive', 'contact'],
            array_keys($response->json()[0])
        );
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(401);
    }

    public function test_search_matches_name_case_insensitively(): void
    {
        $this->actingAsAdmin();

        Product::factory()->create(['name' => 'Alpha', 'slug' => 'alpha']);
        Product::factory()->create(['name' => 'alphaBet', 'slug' => 'alphabet']);
        Product::factory()->create(['name' => 'Beta', 'slug' => 'beta']);

        $response = $this->getJson('/api/products?search=alpha');

        $response->assertStatus(200);

        $ids = collect($response->json())->pluck('id')->all();
        $this->assertEqualsCanonicalizing(['alpha', 'alphabet'], $ids);
    }

    public function test_category_filter_by_slug(): void
    {
        $this->actingAsAdmin();

        $running = Category::factory()->create(['slug' => 'running']);
        $casual = Category::factory()->create(['slug' => 'casual']);

        Product::factory()->for($running)->create(['slug' => 'shoe']);
        Product::factory()->for($casual)->create(['slug' => 'sandal']);

        $response = $this->getJson('/api/products?category=running');

        $response->assertStatus(200);

        $ids = collect($response->json())->pluck('id')->all();
        $this->assertSame(['shoe'], $ids);
    }

    public function test_sort_by_price_desc(): void
    {
        $this->actingAsAdmin();

        Product::factory()->create(['slug' => 'p10', 'price' => 10]);
        Product::factory()->create(['slug' => 'p30', 'price' => 30]);
        Product::factory()->create(['slug' => 'p20', 'price' => 20]);

        $response = $this->getJson('/api/products?sortBy=price&sortOrder=desc');

        $response->assertStatus(200);

        $prices = collect($response->json())->pluck('price')->all();
        // json_encode drops the trailing .0 on whole floats, so the decoded
        // values come back as ints; assert numeric equality, not type.
        $this->assertEquals([30, 20, 10], $prices);
    }

    public function test_default_sort_is_name_ascending(): void
    {
        $this->actingAsAdmin();

        Product::factory()->create(['name' => 'Charlie', 'slug' => 'c']);
        Product::factory()->create(['name' => 'Alpha', 'slug' => 'a']);
        Product::factory()->create(['name' => 'Bravo', 'slug' => 'b']);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);

        $names = collect($response->json())->pluck('name')->all();
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $names);
    }

    public function test_invalid_sort_by_returns_422(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/products?sortBy=invented');

        $response->assertStatus(422);
    }

    public function test_invalid_sort_order_returns_422(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/products?sortOrder=sideways');

        $response->assertStatus(422);
    }
}
