<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_ai_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('lead_id')->unique();
            $table->text('summary')->nullable();
            $table->string('next_action_title', 80)->nullable();
            $table->string('next_action_reason', 180)->nullable();
            $table->string('priority', 16)->nullable();
            $table->json('highlights')->nullable();
            $table->json('attention_points')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->string('model')->nullable();
            $table->string('prompt_version', 32)->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
        });

        Schema::create('lead_ai_summary_generations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('lead_id');
            $table->unsignedBigInteger('lead_ai_summary_id')->nullable();
            $table->string('status', 32)->default('processing')->index();
            $table->char('input_hash', 64)->nullable()->index();
            $table->json('context_snapshot')->nullable();
            $table->longText('raw_response')->nullable();
            $table->json('parsed_response')->nullable();
            $table->string('model')->nullable();
            $table->string('prompt_version', 32);
            $table->unsignedInteger('tokens_input')->nullable();
            $table->unsignedInteger('tokens_output')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
            $table->foreign('lead_ai_summary_id')
                ->references('id')
                ->on('lead_ai_summaries')
                ->nullOnDelete();
        });

        Schema::create('lead_ai_feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('lead_id');
            $table->unsignedInteger('user_id');
            $table->text('feedback');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('included_in_generation_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['lead_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_ai_feedback');
        Schema::dropIfExists('lead_ai_summary_generations');
        Schema::dropIfExists('lead_ai_summaries');
    }
};
