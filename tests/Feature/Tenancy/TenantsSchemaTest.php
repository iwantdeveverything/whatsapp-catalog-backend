<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenants_table_exists_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('tenants'));
        $this->assertTrue(Schema::hasColumn('tenants', 'id'));
        $this->assertTrue(Schema::hasColumn('tenants', 'name'));
        $this->assertTrue(Schema::hasColumn('tenants', 'slug'));
        $this->assertTrue(Schema::hasColumn('tenants', 'status'));
        $this->assertTrue(Schema::hasColumn('tenants', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('tenants', 'created_at'));
        $this->assertTrue(Schema::hasColumn('tenants', 'updated_at'));
    }

    public function test_tenants_slug_is_globally_unique(): void
    {
        Tenant::factory()->create(['slug' => 'acme']);

        $this->expectException(QueryException::class);

        Tenant::factory()->create(['slug' => 'acme']);
    }

    public function test_tenants_status_defaults_to_active(): void
    {
        Tenant::factory()->create(['name' => 'Defaulted', 'slug' => 'defaulted']);

        $this->assertDatabaseHas('tenants', [
            'slug' => 'defaulted',
            'status' => 'active',
        ]);
    }
}
