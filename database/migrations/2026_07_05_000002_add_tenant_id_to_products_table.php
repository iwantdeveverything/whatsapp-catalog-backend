<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * tenant_id is NULLABLE in this slice: the auto-fill creating hook
     * (BelongsToTenant, slice 2) does not exist yet and the controllers still
     * create rows without a tenant. Slice 2 adds the hook and a follow-up
     * migration tightening the column to NOT NULL once every write path fills it.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained('tenants')
                ->restrictOnDelete();
            $table->index('tenant_id');
        });

        // Drop the global slug unique inherited from the reconcile migration.
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['slug']);
        });

        // Composite uniqueness scoped to live rows only, so a soft-deleted slug
        // can be reused within the same tenant. Laravel's fluent unique() cannot
        // express a WHERE clause; the raw partial index works on both SQLite
        // (3.8+) and PostgreSQL.
        DB::statement('CREATE UNIQUE INDEX products_tenant_slug_unique ON products (tenant_id, slug) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS products_tenant_slug_unique');

        Schema::table('products', function (Blueprint $table) {
            $table->unique('slug');
            $table->dropIndex(['tenant_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
