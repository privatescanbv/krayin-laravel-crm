<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_product_activities', function (Blueprint $table) {
            $table->unsignedInteger('activity_id');
            $table->foreign('activity_id')->references('id')->on('activities')->onDelete('cascade');

            $table->unsignedBigInteger('partner_product_id');
            $table->foreign('partner_product_id')->references('id')->on('partner_products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_product_activities');
    }
};
