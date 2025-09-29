<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_partner_product', function (Blueprint $table) {
            $table->unsignedBigInteger('clinic_id');
            $table->unsignedBigInteger('partner_product_id');

            $table->foreign('clinic_id')->references('id')->on('clinics')->onDelete('cascade');
            $table->foreign('partner_product_id')->references('id')->on('partner_products')->onDelete('cascade');

            $table->primary(['clinic_id', 'partner_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_partner_product');
    }
};
