<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inkoop_invoice_item_crm_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id')->nullable();
            $table->unsignedBigInteger('inkoop_invoice_item_id');
            $table->integer('product_id')->unsigned()->nullable();
            $table->string('crm_id')->nullable();
            $table->string('crm_status')->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->timestamps();

            $table->foreign('clinic_id')->references('id')->on('clinics')->nullOnDelete();
            $table->foreign('inkoop_invoice_item_id')->references('id')->on('inkoop_invoice_items')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->index(['inkoop_invoice_item_id', 'crm_id'], 'iii_crm_products_item_crm_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inkoop_invoice_item_crm_products');
    }
};
