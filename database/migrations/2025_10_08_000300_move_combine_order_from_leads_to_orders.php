<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure the orders table exists before attempting to modify it
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'combine_order')) {
                    $table->boolean('combine_order')->default(true)->after('sales_lead_id');
                }
            });
        }

        // Drop column from leads if present
        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'combine_order')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('combine_order');
            });
        }
    }

    public function down(): void
    {
        // Re-add to leads if missing
        if (Schema::hasTable('leads') && ! Schema::hasColumn('leads', 'combine_order')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->boolean('combine_order')->default(true)->after('updated_by');
            });
        }

        // Remove from orders if present
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'combine_order')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('combine_order');
            });
        }
    }
};


