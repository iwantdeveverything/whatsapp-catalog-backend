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
     * tenant_id is NULLABLE in this slice (see products migration for rationale).
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained('tenants')
                ->restrictOnDelete();
            $table->index('tenant_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['slug']);
        });

        // Partial composite unique (tenant_id, slug) over live rows only.
        //
        // HONESTY / TEMPORARY GAP: this composite index is INERT for rows with
        // tenant_id = NULL. NULLs are DISTINCT in SQL unique indexes on both
        // SQLite and PostgreSQL, so two (NULL, 'drinks') rows never collide.
        // Because tenant_id is NULLABLE in this slice and the live write path
        // still creates rows with tenant_id = NULL (the BelongsToTenant creating
        // hook that auto-fills tenant_id lands in Slice 2), there is NO atomic
        // DB-level slug uniqueness for tenant_id = NULL rows — only the racy
        // application-level SlugGenerator. Enforcement is restored once Slice 2
        // makes tenant_id NOT NULL and every write path populates it.
        DB::statement('CREATE UNIQUE INDEX categories_tenant_slug_unique ON categories (tenant_id, slug) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS categories_tenant_slug_unique');

        Schema::table('categories', function (Blueprint $table) {
            $table->unique('slug');
            $table->dropIndex(['tenant_id']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
