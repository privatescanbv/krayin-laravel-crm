<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inkoop_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id');
            $table->unsignedBigInteger('inkoop_invoice_id');
            $table->unsignedBigInteger('person_id')->nullable();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('total_price', 12, 2)->nullable();
            $table->string('name')->nullable();
            $table->date('date')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('clinic_id')->references('id')->on('clinics')->cascadeOnDelete();
            $table->foreign('inkoop_invoice_id')->references('id')->on('inkoop_invoices')->cascadeOnDelete();
            $table->foreign('person_id')->references('id')->on('inkoop_persons')->nullOnDelete();
            $table->index(['inkoop_invoice_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inkoop_invoice_items');
    }
};
