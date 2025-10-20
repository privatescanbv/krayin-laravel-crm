<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if the column already exists before adding it
        if (!Schema::hasColumn('partner_products', 'deleted_at')) {
            try {
                Schema::table('partner_products', function (Blueprint $table) {
                    $table->timestamp('deleted_at')->nullable();
                });
            } catch (\Exception $e) {
                // If the column already exists or there's an error, log it but don't fail
                \Log::info('Could not add deleted_at column to partner_products: ' . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('partner_products', 'deleted_at')) {
            Schema::table('partner_products', function (Blueprint $table) {
                $table->dropColumn('deleted_at');
            });
        }
    }
};