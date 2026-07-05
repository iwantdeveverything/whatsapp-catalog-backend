<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_active_tenant_by_default(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertTrue($tenant->exists);
        $this->assertNotEmpty($tenant->name);
        $this->assertNotEmpty($tenant->slug);
        $this->assertSame('active', $tenant->status);
        $this->assertNull($tenant->deleted_at);
    }

    public function test_suspended_state_sets_status_to_suspended(): void
    {
        $tenant = Tenant::factory()->suspended()->create();

        $this->assertSame('suspended', $tenant->status);
    }

    public function test_trashed_state_soft_deletes_the_tenant(): void
    {
        $tenant = Tenant::factory()->trashed()->create();

        $this->assertNotNull($tenant->deleted_at);
        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
    }

    public function test_active_scope_returns_only_active_non_trashed_tenants(): void
    {
        $active = Tenant::factory()->create(['slug' => 'active-one']);
        Tenant::factory()->suspended()->create(['slug' => 'suspended-one']);
        Tenant::factory()->trashed()->create(['slug' => 'trashed-one']);

        $result = Tenant::active()->get();

        $this->assertCount(1, $result);
        $this->assertTrue($result->contains('id', $active->id));
    }

    public function test_factory_generates_unique_slugs_across_instances(): void
    {
        $tenants = Tenant::factory()->count(3)->create();

        $slugs = $tenants->pluck('slug');

        $this->assertCount(3, $slugs->unique());
    }
}
