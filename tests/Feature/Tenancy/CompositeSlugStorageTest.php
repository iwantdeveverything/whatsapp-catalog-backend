<?php

namespace Tests\Feature\Tenancy;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DB-level storage half of MT-UNIQ-001. The scope engine (slice 2) does not
 * exist yet, so tenant_id is set explicitly on each insert. The HTTP 422
 * behavior half of MT-UNIQ-001 is covered later in the uniqueness slice.
 */
class CompositeSlugStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_tenants_may_share_the_same_product_slug(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
        $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);

        $a = Product::factory()->create(['tenant_id' => $tenantA->id, 'slug' => 'pizza']);
        $b = Product::factory()->create(['tenant_id' => $tenantB->id, 'slug' => 'pizza']);

        $this->assertNotSame($a->id, $b->id);
        $this->assertSame('pizza', $a->slug);
        $this->assertSame('pizza', $b->slug);
    }

    public function test_one_tenant_may_not_duplicate_a_product_slug(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'tenant-a']);

        Product::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'pizza']);

        $this->expectException(QueryException::class);

        Product::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'pizza']);
    }

    public function test_soft_deleted_product_slug_can_be_recreated_within_tenant(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'tenant-a']);

        $first = Product::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'pizza']);
        $first->delete();

        $second = Product::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'pizza']);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('pizza', $second->slug);
    }

    public function test_two_tenants_may_share_the_same_category_slug(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
        $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);

        $a = Category::factory()->create(['tenant_id' => $tenantA->id, 'slug' => 'drinks']);
        $b = Category::factory()->create(['tenant_id' => $tenantB->id, 'slug' => 'drinks']);

        $this->assertNotSame($a->id, $b->id);
    }

    public function test_one_tenant_may_not_duplicate_a_category_slug(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'tenant-a']);

        Category::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'drinks']);

        $this->expectException(QueryException::class);

        Category::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'drinks']);
    }

    public function test_soft_deleted_category_slug_can_be_recreated_within_tenant(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'tenant-a']);

        $first = Category::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'drinks']);
        $first->delete();

        $second = Category::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'drinks']);

        $this->assertNotSame($first->id, $second->id);
    }

    public function test_two_tenants_may_share_the_same_setting_key(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
        $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);

        $a = Setting::forceCreate(['tenant_id' => $tenantA->id, 'key' => 'catalog_name', 'value' => ['n' => 'A']]);
        $b = Setting::forceCreate(['tenant_id' => $tenantB->id, 'key' => 'catalog_name', 'value' => ['n' => 'B']]);

        $this->assertNotSame($a->id, $b->id);
    }

    public function test_one_tenant_may_not_duplicate_a_setting_key(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'tenant-a']);

        Setting::forceCreate(['tenant_id' => $tenant->id, 'key' => 'catalog_name', 'value' => ['n' => 'A']]);

        $this->expectException(QueryException::class);

        Setting::forceCreate(['tenant_id' => $tenant->id, 'key' => 'catalog_name', 'value' => ['n' => 'B']]);
    }
}
