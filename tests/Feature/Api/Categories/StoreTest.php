<?php

namespace Tests\Feature\Api\Categories;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_category_and_derives_slug_from_name(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/categories', [
            'name' => 'Running',
            'description' => 'Sport shoes',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('slug', 'running')
            ->assertJsonPath('id', 'running')
            ->assertJsonPath('name', 'Running')
            ->assertJsonPath('description', 'Sport shoes')
            ->assertJsonPath('productCount', 0);

        $this->assertDatabaseHas('categories', ['slug' => 'running', 'name' => 'Running']);
    }

    public function test_store_defaults_is_active_to_true_at_db_level_without_exposing_it(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/categories', ['name' => 'Casual']);

        $response->assertStatus(201)
            ->assertJsonMissingPath('isActive');

        $this->assertDatabaseHas('categories', [
            'slug' => 'casual',
            'is_active' => true,
        ]);
    }

    public function test_store_ignores_client_supplied_slug(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/categories', [
            'name' => 'Running',
            'slug' => 'hacked-slug',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('slug', 'running')
            ->assertJsonPath('id', 'running');

        $this->assertDatabaseHas('categories', ['slug' => 'running']);
        $this->assertDatabaseMissing('categories', ['slug' => 'hacked-slug']);
    }

    public function test_store_collision_suffixes_the_slug(): void
    {
        $this->actingAsAdmin();

        Category::factory()->create(['name' => 'Running', 'slug' => 'running']);

        $response = $this->postJson('/api/categories', ['name' => 'Running']);

        $response->assertStatus(201)
            ->assertJsonPath('slug', 'running-2');
    }

    public function test_store_returns_422_when_name_missing(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/categories', ['description' => 'no name']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/categories', ['name' => 'Running']);

        $response->assertStatus(401);
    }
}
