<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'partner_product_id')) {
                $table->unsignedBigInteger('partner_product_id')->nullable()->after('product_id');
                $table->foreign('partner_product_id')
                    ->references('id')
                    ->on('partner_products')
                    ->nullOnDelete();
                $table->index('partner_product_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'partner_product_id')) {
                $table->dropForeign(['partner_product_id']);
                $table->dropIndex(['partner_product_id']);
                $table->dropColumn('partner_product_id');
            }
        });
    }
};
