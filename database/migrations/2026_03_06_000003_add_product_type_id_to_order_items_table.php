<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'product_type_id')) {
                return;
            }

            $table->unsignedBigInteger('product_type_id')->nullable()->after('product_id');
            $table->foreign('product_type_id')->references('id')->on('product_types')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'product_type_id')) {
                return;
            }

            $table->dropForeign(['product_type_id']);
            $table->dropColumn('product_type_id');
        });
    }
};
