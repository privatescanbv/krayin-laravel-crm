<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_products', function (Blueprint $table) {
            $table->decimal('related_sales_price', 12, 2)->default(0)->after('sales_price');
        });
    }

    public function down(): void
    {
        Schema::table('partner_products', function (Blueprint $table) {
            $table->dropColumn('related_sales_price');
        });
    }
};
