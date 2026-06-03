<?php

namespace Tests\Feature\Models;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_key_name_is_slug(): void
    {
        $this->assertSame('slug', (new Product)->getRouteKeyName());
    }

    public function test_soft_delete_hides_then_with_trashed_reveals(): void
    {
        $product = Product::factory()->create();
        $id = $product->id;

        $product->delete();

        $this->assertNull(Product::query()->find($id));
        $this->assertNotNull(Product::withTrashed()->find($id));
        $this->assertNotNull(Product::withTrashed()->find($id)->deleted_at);
    }

    public function test_is_active_casts_to_boolean(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $this->assertIsBool($product->fresh()->is_active);
        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_scope_active_excludes_inactive_and_trashed(): void
    {
        $active = Product::factory()->create(['is_active' => true]);
        Product::factory()->create(['is_active' => false]);
        Product::factory()->create()->delete();

        $results = Product::query()->active()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($active));
    }

    public function test_scope_search_matches_name_case_insensitively(): void
    {
        $alpha = Product::factory()->create(['name' => 'Alpha']);
        $alphabet = Product::factory()->create(['name' => 'alphaBet']);
        Product::factory()->create(['name' => 'Beta']);

        $results = Product::query()->search('alpha')->get();

        $this->assertCount(2, $results);
        $this->assertEqualsCanonicalizing(
            [$alpha->id, $alphabet->id],
            $results->pluck('id')->all()
        );
    }

    public function test_scope_search_with_null_or_empty_term_is_a_noop(): void
    {
        Product::factory()->count(3)->create();

        $this->assertCount(3, Product::query()->search(null)->get());
        $this->assertCount(3, Product::query()->search('')->get());
    }

    public function test_scope_ordered_sorts_by_field_and_direction(): void
    {
        Product::factory()->create(['name' => 'B-name', 'price' => 10]);
        Product::factory()->create(['name' => 'A-name', 'price' => 30]);
        Product::factory()->create(['name' => 'C-name', 'price' => 20]);

        $byPriceDesc = Product::query()->ordered('price', 'desc')->pluck('price');
        $this->assertSame(['30.00', '20.00', '10.00'], $byPriceDesc->all());

        $byNameAsc = Product::query()->ordered('name', 'asc')->pluck('name');
        $this->assertSame(['A-name', 'B-name', 'C-name'], $byNameAsc->all());
    }
}
