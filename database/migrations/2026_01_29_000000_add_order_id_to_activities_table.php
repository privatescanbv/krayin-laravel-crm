<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('activities', 'order_id')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->unsignedBigInteger('order_id')->nullable()->after('sales_lead_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('activities', 'order_id')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->dropColumn('order_id');
            });
        }
    }
};
