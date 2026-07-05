<?php

namespace Tests\Feature\Tenancy;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * VISIBILITY TEST — documents a KNOWN TEMPORARY GAP, not desired behavior.
 *
 * In Slice 1 `tenant_id` is NULLABLE and the composite unique indexes
 * `(tenant_id, slug)` are partial/composite indexes. NULLs are DISTINCT in SQL
 * unique indexes on both SQLite and PostgreSQL, so two rows with tenant_id = NULL
 * and the SAME slug DO NOT collide — the index is inert for NULL-tenant rows.
 *
 * This test asserts that current (undesired but temporary) reality so the gap is
 * visible in the suite instead of hidden. During a Slice-1-only deploy window the
 * live write path creates rows with tenant_id = NULL and has NO atomic DB-level
 * slug uniqueness — only the racy application-level SlugGenerator.
 *
 * SLICE 2 CLOSES THIS GAP: once the BelongsToTenant creating hook auto-fills
 * tenant_id and the follow-up migration tightens the column to NOT NULL, rows
 * with tenant_id = NULL become impossible to insert, so this test will no longer
 * be constructible. WHEN SLICE 2 LANDS, THIS TEST MUST BE UPDATED OR REMOVED.
 */
class NullTenantSlugGapTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_null_tenant_products_with_same_slug_both_persist(): void
    {
        $a = Product::factory()->create(['tenant_id' => null, 'slug' => 'pizza']);
        $b = Product::factory()->create(['tenant_id' => null, 'slug' => 'pizza']);

        // Both rows persist: the composite (tenant_id, slug) unique index does NOT
        // guard NULL-tenant rows today. This is the temporary gap closed in Slice 2.
        $this->assertNotSame($a->id, $b->id);
        $this->assertNull($a->tenant_id);
        $this->assertNull($b->tenant_id);
        $this->assertSame(2, Product::whereNull('tenant_id')->where('slug', 'pizza')->count());
    }

    public function test_two_null_tenant_categories_with_same_slug_both_persist(): void
    {
        $a = Category::factory()->create(['tenant_id' => null, 'slug' => 'drinks']);
        $b = Category::factory()->create(['tenant_id' => null, 'slug' => 'drinks']);

        $this->assertNotSame($a->id, $b->id);
        $this->assertNull($a->tenant_id);
        $this->assertNull($b->tenant_id);
        $this->assertSame(2, Category::whereNull('tenant_id')->where('slug', 'drinks')->count());
    }
}
