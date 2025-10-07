<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('product_partner_product');
    }

    public function down(): void
    {
        Schema::create('product_partner_product', function (Blueprint $table) {
            $table->unsignedInteger('product_id');
            $table->unsignedBigInteger('partner_product_id');

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('partner_product_id')->references('id')->on('partner_products')->onDelete('cascade');

            $table->primary(['product_id', 'partner_product_id'], 'product_partner_product_primary');
        });
    }
};
