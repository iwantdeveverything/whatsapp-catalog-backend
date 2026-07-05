<?php

namespace Tests\Feature\Database;

use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SettingsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('settings'));
        $this->assertTrue(Schema::hasColumn('settings', 'id'));
        $this->assertTrue(Schema::hasColumn('settings', 'key'));
        $this->assertTrue(Schema::hasColumn('settings', 'value'));
        $this->assertTrue(Schema::hasColumn('settings', 'created_at'));
        $this->assertTrue(Schema::hasColumn('settings', 'updated_at'));
    }

    public function test_settings_key_is_unique_within_a_tenant(): void
    {
        // Global key uniqueness was replaced by composite (tenant_id, key)
        // uniqueness. Within one tenant a key still collides.
        $tenant = Tenant::factory()->create();

        DB::table('settings')->insert([
            'tenant_id' => $tenant->id,
            'key' => 'catalog_name',
            'value' => json_encode('Shop'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('settings')->insert([
            'tenant_id' => $tenant->id,
            'key' => 'catalog_name',
            'value' => json_encode('Other'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
