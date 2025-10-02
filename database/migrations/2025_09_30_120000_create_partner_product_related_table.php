<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_product_related', function (Blueprint $table) {
            $table->unsignedBigInteger('partner_product_id');
            $table->unsignedBigInteger('related_product_id');

            $table->foreign('partner_product_id')
                ->references('id')
                ->on('partner_products')
                ->onDelete('cascade');

            $table->foreign('related_product_id')
                ->references('id')
                ->on('partner_products')
                ->onDelete('cascade');

            $table->primary(['partner_product_id', 'related_product_id'], 'partner_product_related_primary');

            // Prevent duplicate entries in reverse order
            $table->unique(['related_product_id', 'partner_product_id'], 'partner_product_related_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_product_related');
    }
};
