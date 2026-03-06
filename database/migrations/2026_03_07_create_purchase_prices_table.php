<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_prices', function (Blueprint $table) {
            $table->id();
            $table->string('priceable_type');
            $table->unsignedBigInteger('priceable_id');
            $table->string('type')->default('main');
            $table->decimal('purchase_price_misc', 10, 2)->nullable();
            $table->decimal('purchase_price_doctor', 10, 2)->nullable();
            $table->decimal('purchase_price_cardiology', 10, 2)->nullable();
            $table->decimal('purchase_price_clinic', 10, 2)->nullable();
            $table->decimal('purchase_price_radiology', 10, 2)->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['priceable_type', 'priceable_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_prices');
    }
};
