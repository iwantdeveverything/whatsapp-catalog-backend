<?php

namespace Tests\Feature\Api\Categories;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_update_keeps_slug_when_name_unchanged(): void
    {
        $this->actingAsAdmin();

        Category::factory()->create([
            'name' => 'Running',
            'slug' => 'running',
            'description' => 'Old',
        ]);

        $response = $this->putJson('/api/categories/running', ['description' => 'Updated']);

        $response->assertStatus(200)
            ->assertJsonPath('slug', 'running')
            ->assertJsonPath('description', 'Updated');

        $this->assertDatabaseHas('categories', ['slug' => 'running', 'description' => 'Updated']);
    }

    public function test_rename_regenerates_slug(): void
    {
        $this->actingAsAdmin();

        Category::factory()->create(['name' => 'Running', 'slug' => 'running']);

        $response = $this->putJson('/api/categories/running', ['name' => 'Trail Running']);

        $response->assertStatus(200)
            ->assertJsonPath('name', 'Trail Running')
            ->assertJsonPath('slug', 'trail-running');
    }

    public function test_rename_collision_suffixes_the_new_slug(): void
    {
        $this->actingAsAdmin();

        Category::factory()->create(['name' => 'Casual', 'slug' => 'casual']);
        Category::factory()->create(['name' => 'Running', 'slug' => 'running']);

        $response = $this->putJson('/api/categories/running', ['name' => 'Casual']);

        $response->assertStatus(200)
            ->assertJsonPath('slug', 'casual-2');
    }

    public function test_update_returns_404_for_unknown_slug(): void
    {
        $this->actingAsAdmin();

        $response = $this->putJson('/api/categories/nonexistent', ['name' => 'X']);

        $response->assertStatus(404);
    }

    public function test_update_requires_authentication(): void
    {
        Category::factory()->create(['slug' => 'running']);

        $response = $this->putJson('/api/categories/running', ['name' => 'X']);

        $response->assertStatus(401);
    }
}
