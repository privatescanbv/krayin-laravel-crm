<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add combine_order column to orders table
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'combine_order')) {
                $table->boolean('combine_order')->default(true)->after('sales_lead_id');
            }
        });

        // Remove combine_order column from leads table
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'combine_order')) {
                $table->dropColumn('combine_order');
            }
        });
    }

    public function down(): void
    {
        // Add combine_order column back to leads table
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'combine_order')) {
                $table->boolean('combine_order')->default(true)->after('updated_by');
            }
        });

        // Remove combine_order column from orders table
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'combine_order')) {
                $table->dropColumn('combine_order');
            }
        });
    }
};
