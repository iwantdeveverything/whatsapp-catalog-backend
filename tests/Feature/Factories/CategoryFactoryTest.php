<?php

namespace Tests\Feature\Factories;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_persisted_category_with_unique_slug(): void
    {
        $category = Category::factory()->create();

        $this->assertTrue($category->exists);
        $this->assertNotEmpty($category->name);
        $this->assertNotEmpty($category->slug);
    }

    public function test_factory_produces_unique_slugs_across_invocations(): void
    {
        $a = Category::factory()->create();
        $b = Category::factory()->create();

        $this->assertNotSame($a->slug, $b->slug, 'Two factory runs must yield distinct slugs');
        $this->assertSame(2, Category::query()->count());
    }
}
