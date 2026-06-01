<?php

namespace Tests\Feature\Database;

use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CategoriesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_table_has_reconciled_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('categories', 'slug'));
        $this->assertTrue(Schema::hasColumn('categories', 'description'));
        $this->assertTrue(Schema::hasColumn('categories', 'is_active'));
        $this->assertTrue(Schema::hasColumn('categories', 'deleted_at'));
    }

    public function test_categories_slug_is_unique(): void
    {
        Category::factory()->create(['slug' => 'duplicate-slug']);

        $this->expectException(QueryException::class);

        Category::factory()->create(['slug' => 'duplicate-slug']);
    }

    public function test_categories_is_active_defaults_to_true(): void
    {
        DB::table('categories')->insert([
            'name' => 'Defaulted Category',
            'slug' => 'defaulted-category',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('categories')->where('slug', 'defaulted-category')->first();

        $this->assertEquals(1, $row->is_active);
    }
}
