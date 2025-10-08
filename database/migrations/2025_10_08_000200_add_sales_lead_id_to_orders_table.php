<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('sales_lead_id')->nullable()->after('sales_order_id');
            $table->foreign('sales_lead_id')->references('id')->on('salesleads')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'sales_lead_id')) {
                $table->dropForeign(['sales_lead_id']);
                $table->dropColumn('sales_lead_id');
            }
        });
    }
};
