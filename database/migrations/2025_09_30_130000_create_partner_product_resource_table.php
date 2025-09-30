<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_product_resource', function (Blueprint $table) {
            $table->unsignedBigInteger('partner_product_id');
            $table->unsignedBigInteger('resource_id');

            $table->foreign('partner_product_id')
                ->references('id')
                ->on('partner_products')
                ->onDelete('cascade');

            $table->foreign('resource_id')
                ->references('id')
                ->on('resources')
                ->onDelete('cascade');

            $table->primary(['partner_product_id', 'resource_id'], 'partner_product_resource_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_product_resource');
    }
};