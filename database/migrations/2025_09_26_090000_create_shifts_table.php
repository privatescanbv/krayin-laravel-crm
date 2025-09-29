<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('resource_id');
            $table->text('notes')->nullable();
            $table->boolean('available')->default(true);

            // Period (whole days)
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();

            // Per-weekday time blocks: { "1": [{"from":"08:00","to":"12:00"}], "2": [...], ... }
            $table->json('weekday_time_blocks')->nullable();

            // Audit trail fields
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();

            $table->timestamps();

            $table->foreign('resource_id')
                ->references('id')->on('resources')
                ->onDelete('cascade');

            // Optional FKs for audit users (keep nullable, do not cascade)
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');

            $table->index(['resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
