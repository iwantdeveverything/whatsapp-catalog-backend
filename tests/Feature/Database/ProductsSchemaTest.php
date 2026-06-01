<?php

namespace Tests\Feature\Database;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_table_has_reconciled_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('products', 'slug'));
        $this->assertTrue(Schema::hasColumn('products', 'whatsapp'));
        $this->assertTrue(Schema::hasColumn('products', 'phone'));
        $this->assertTrue(Schema::hasColumn('products', 'is_active'));
        $this->assertTrue(Schema::hasColumn('products', 'deleted_at'));
    }

    public function test_products_table_drops_legacy_status_column(): void
    {
        $this->assertFalse(Schema::hasColumn('products', 'status'));
    }

    public function test_products_slug_is_unique(): void
    {
        Product::factory()->create(['slug' => 'duplicate-slug']);

        $this->expectException(QueryException::class);

        Product::factory()->create(['slug' => 'duplicate-slug']);
    }

    public function test_products_is_active_defaults_to_true(): void
    {
        $category = Category::factory()->create();

        DB::table('products')->insert([
            'category_id' => $category->id,
            'name' => 'Defaulted Product',
            'slug' => 'defaulted-product',
            'price' => 10.00,
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('products')->where('slug', 'defaulted-product')->first();

        $this->assertEquals(1, $row->is_active);
    }
}
