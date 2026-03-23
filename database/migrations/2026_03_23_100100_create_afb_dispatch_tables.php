<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('afb_dispatches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id');
            $table->unsignedInteger('email_id')->nullable();
            $table->string('type', 20);
            $table->string('status', 20);
            $table->json('order_ids')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('clinic_id')->references('id')->on('clinics')->cascadeOnDelete();
            $table->foreign('email_id')->references('id')->on('emails')->nullOnDelete();
            $table->index(['clinic_id', 'status', 'created_at']);
        });

        Schema::create('afb_dispatch_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('afb_dispatch_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('clinic_id');
            $table->unsignedInteger('person_id')->nullable();
            $table->string('patient_name')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('afb_dispatch_id')->references('id')->on('afb_dispatches')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('clinic_id')->references('id')->on('clinics')->cascadeOnDelete();
            $table->foreign('person_id')->references('id')->on('persons')->nullOnDelete();
            $table->index(['clinic_id', 'order_id']);
            $table->index(['order_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afb_dispatch_orders');
        Schema::dropIfExists('afb_dispatches');
    }
};
