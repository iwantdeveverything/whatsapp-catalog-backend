<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->string('slug')->unique()->after('name');
            $table->string('whatsapp')->nullable()->after('currency');
            $table->string('phone')->nullable()->after('whatsapp');
            $table->boolean('is_active')->default(true)->after('phone');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('is_active');
            $table->dropColumn('phone');
            $table->dropColumn('whatsapp');
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
            $table->string('status')->default('active')->after('currency');
        });
    }
};
