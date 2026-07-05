<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantColumnsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_have_tenant_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('products', 'tenant_id'));
    }

    public function test_categories_have_tenant_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('categories', 'tenant_id'));
    }

    public function test_settings_have_tenant_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('settings', 'tenant_id'));
    }

    public function test_users_have_tenant_id_and_role_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'tenant_id'));
        $this->assertTrue(Schema::hasColumn('users', 'role'));
    }

    public function test_users_role_defaults_to_owner(): void
    {
        $user = User::factory()->create();

        $this->assertSame('owner', $user->fresh()->role);
    }
}
