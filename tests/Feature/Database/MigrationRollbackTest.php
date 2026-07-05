<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exercises the reversibility of the PR #3 reconciliation migrations.
 *
 * Does NOT use RefreshDatabase: it drives migrate / rollback directly so the
 * down() methods of the three new migrations are actually executed.
 */
class MigrationRollbackTest extends TestCase
{
    public function test_rollback_then_remigrate_round_trip(): void
    {
        Artisan::call('migrate:fresh');

        // Forward state: reconciled schema + tenancy foundation present.
        $this->assertTrue(Schema::hasColumn('products', 'slug'));
        $this->assertFalse(Schema::hasColumn('products', 'status'));
        $this->assertTrue(Schema::hasColumn('categories', 'is_active'));
        $this->assertTrue(Schema::hasTable('settings'));
        $this->assertTrue(Schema::hasTable('tenants'));
        $this->assertTrue(Schema::hasColumn('products', 'tenant_id'));

        // Roll back the five tenancy migrations plus the three reconciliation
        // migrations (tenancy migrations were layered on top of reconcile).
        Artisan::call('migrate:rollback', ['--step' => 8]);

        $this->assertFalse(Schema::hasTable('tenants'));
        $this->assertFalse(Schema::hasColumn('products', 'tenant_id'));
        $this->assertFalse(Schema::hasTable('settings'));
        $this->assertFalse(Schema::hasColumn('products', 'slug'));
        $this->assertFalse(Schema::hasColumn('categories', 'is_active'));
        // status is restored by the products reconcile down() migration.
        $this->assertTrue(Schema::hasColumn('products', 'status'));

        // Re-apply: end state must match the forward state.
        Artisan::call('migrate');

        $this->assertTrue(Schema::hasColumn('products', 'slug'));
        $this->assertFalse(Schema::hasColumn('products', 'status'));
        $this->assertTrue(Schema::hasColumn('categories', 'is_active'));
        $this->assertTrue(Schema::hasTable('settings'));
        $this->assertTrue(Schema::hasTable('tenants'));
        $this->assertTrue(Schema::hasColumn('products', 'tenant_id'));
    }
}
