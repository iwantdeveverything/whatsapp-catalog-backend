<?php

namespace Tests\Feature\Api\Categories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_destroy_returns_409_when_active_products_attached(): void
    {
        $this->actingAsAdmin();

        $category = Category::factory()->create(['slug' => 'running']);
        Product::factory()->count(2)->for($category)->create(['is_active' => true]);

        $response = $this->deleteJson('/api/categories/running');

        $response->assertStatus(409)
            ->assertExactJson(['error' => 'Cannot delete category with existing products']);

        $this->assertDatabaseHas('categories', ['slug' => 'running', 'deleted_at' => null]);
    }

    public function test_destroy_returns_409_when_only_inactive_products_attached(): void
    {
        $this->actingAsAdmin();

        $category = Category::factory()->create(['slug' => 'casual']);
        Product::factory()->for($category)->create(['is_active' => false]);

        $response = $this->deleteJson('/api/categories/casual');

        $response->assertStatus(409)
            ->assertExactJson(['error' => 'Cannot delete category with existing products']);

        $this->assertDatabaseHas('categories', ['slug' => 'casual', 'deleted_at' => null]);
    }

    public function test_destroy_soft_deletes_when_no_products(): void
    {
        $this->actingAsAdmin();

        $category = Category::factory()->create(['slug' => 'empty']);

        $response = $this->deleteJson('/api/categories/empty');

        $response->assertStatus(204);

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_destroy_succeeds_when_only_soft_deleted_products_attached(): void
    {
        $this->actingAsAdmin();

        $category = Category::factory()->create(['slug' => 'archived']);
        Product::factory()->for($category)->create()->delete();

        $response = $this->deleteJson('/api/categories/archived');

        $response->assertStatus(204);

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_destroy_returns_404_for_unknown_slug(): void
    {
        $this->actingAsAdmin();

        $response = $this->deleteJson('/api/categories/nonexistent');

        $response->assertStatus(404);
    }

    public function test_destroy_requires_authentication(): void
    {
        Category::factory()->create(['slug' => 'running']);

        $response = $this->deleteJson('/api/categories/running');

        $response->assertStatus(401);
    }
}
