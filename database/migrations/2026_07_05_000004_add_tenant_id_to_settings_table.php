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
