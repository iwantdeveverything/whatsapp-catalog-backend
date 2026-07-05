<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * settings has no soft-deletes, so a plain composite unique (tenant_id, key)
     * is sufficient (no partial index needed). tenant_id is NULLABLE in this
     * slice (see products migration for rationale).
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained('tenants')
                ->restrictOnDelete();
            $table->index('tenant_id');
        });

        // HONESTY / TEMPORARY GAP: this composite unique (tenant_id, key) is
        // INERT for rows with tenant_id = NULL. NULLs are DISTINCT in SQL unique
        // indexes on both SQLite and PostgreSQL, so two (NULL, 'catalog_name')
        // rows never collide. Because tenant_id is NULLABLE in this slice and the
        // live write path still creates rows with tenant_id = NULL (the
        // BelongsToTenant creating hook that auto-fills tenant_id lands in
        // Slice 2), there is NO atomic DB-level key uniqueness for
        // tenant_id = NULL rows. Enforcement is restored once Slice 2 makes
        // tenant_id NOT NULL and every write path populates it.
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->unique(['tenant_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'key']);
            $table->unique('key');
            $table->dropIndex(['tenant_id']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
