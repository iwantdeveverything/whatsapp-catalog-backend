<?php

namespace Tests\Feature\Models;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_key_name_is_slug(): void
    {
        $this->assertSame('slug', (new Category)->getRouteKeyName());
    }

    public function test_soft_delete_hides_then_with_trashed_reveals(): void
    {
        $category = Category::factory()->create();
        $id = $category->id;

        $category->delete();

        $this->assertNull(Category::query()->find($id));
        $this->assertNotNull(Category::withTrashed()->find($id));
        $this->assertNotNull(Category::withTrashed()->find($id)->deleted_at);
    }

    public function test_is_active_casts_to_boolean(): void
    {
        $category = Category::factory()->create(['is_active' => true]);

        $this->assertIsBool($category->fresh()->is_active);
        $this->assertTrue($category->fresh()->is_active);
    }
}
