<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inkoop_persons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id');
            $table->unsignedBigInteger('invoice_id');
            $table->string('name')->nullable();
            $table->string('external_id')->nullable();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->date('birthday')->nullable();
            $table->string('crm_id')->nullable();
            $table->timestamps();

            $table->foreign('clinic_id')->references('id')->on('clinics')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('inkoop_invoices')->cascadeOnDelete();
            $table->index(['invoice_id', 'crm_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inkoop_persons');
    }
};
