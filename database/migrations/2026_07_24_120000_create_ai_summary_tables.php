<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the lead-only AI summary tables with polymorphic ones, so a summary can
 * be attached to any subject (lead, person, order, sales lead, ...) without another
 * table per entity. Existing lead rows are copied over before the old tables go.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 64);
            $table->unsignedBigInteger('subject_id');
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

            $table->unique(['subject_type', 'subject_id']);
        });

        Schema::create('ai_summary_generations', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 64);
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('ai_summary_id')->nullable();
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

            $table->index(['subject_type', 'subject_id']);
            $table->foreign('ai_summary_id')->references('id')->on('ai_summaries')->nullOnDelete();
        });

        Schema::create('ai_feedback', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 64);
            $table->unsignedBigInteger('subject_id');
            $table->unsignedInteger('user_id');
            $table->text('feedback');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('included_in_generation_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['subject_type', 'subject_id', 'is_active']);
        });

        $this->copyLeadRows();

        Schema::dropIfExists('lead_ai_feedback');
        Schema::dropIfExists('lead_ai_summary_generations');
        Schema::dropIfExists('lead_ai_summaries');
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_feedback');
        Schema::dropIfExists('ai_summary_generations');
        Schema::dropIfExists('ai_summaries');

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

    /**
     * Move the lead-only rows into the polymorphic tables. Generations keep pointing at
     * the summary they belong to, so the old id has to be translated on the way over.
     */
    private function copyLeadRows(): void
    {
        if (! Schema::hasTable('lead_ai_summaries')) {
            return;
        }

        $summaryIds = [];

        DB::table('lead_ai_summaries')->orderBy('id')->each(function (object $row) use (&$summaryIds) {
            $summaryIds[$row->id] = DB::table('ai_summaries')->insertGetId([
                'subject_type'       => 'leads',
                'subject_id'         => $row->lead_id,
                'summary'            => $row->summary,
                'next_action_title'  => $row->next_action_title,
                'next_action_reason' => $row->next_action_reason,
                'priority'           => $row->priority,
                'highlights'         => $row->highlights,
                'attention_points'   => $row->attention_points,
                'generated_at'       => $row->generated_at,
                'model'              => $row->model,
                'prompt_version'     => $row->prompt_version,
                'status'             => $row->status,
                'last_error'         => $row->last_error,
                'created_at'         => $row->created_at,
                'updated_at'         => $row->updated_at,
            ]);
        });

        if (Schema::hasTable('lead_ai_summary_generations')) {
            DB::table('lead_ai_summary_generations')->orderBy('id')->each(function (object $row) use ($summaryIds) {
                DB::table('ai_summary_generations')->insert([
                    'subject_type'     => 'leads',
                    'subject_id'       => $row->lead_id,
                    'ai_summary_id'    => $summaryIds[$row->lead_ai_summary_id] ?? null,
                    'status'           => $row->status,
                    'input_hash'       => $row->input_hash,
                    'context_snapshot' => $row->context_snapshot,
                    'raw_response'     => $row->raw_response,
                    'parsed_response'  => $row->parsed_response,
                    'model'            => $row->model,
                    'prompt_version'   => $row->prompt_version,
                    'tokens_input'     => $row->tokens_input,
                    'tokens_output'    => $row->tokens_output,
                    'duration_ms'      => $row->duration_ms,
                    'error_message'    => $row->error_message,
                    'started_at'       => $row->started_at,
                    'completed_at'     => $row->completed_at,
                    'created_at'       => $row->created_at,
                    'updated_at'       => $row->updated_at,
                ]);
            });
        }

        if (Schema::hasTable('lead_ai_feedback')) {
            DB::table('lead_ai_feedback')->orderBy('id')->each(function (object $row) {
                DB::table('ai_feedback')->insert([
                    'subject_type'              => 'leads',
                    'subject_id'                => $row->lead_id,
                    'user_id'                   => $row->user_id,
                    'feedback'                  => $row->feedback,
                    'is_active'                 => $row->is_active,
                    'included_in_generation_at' => $row->included_in_generation_at,
                    'created_at'                => $row->created_at,
                    'updated_at'                => $row->updated_at,
                    'deleted_at'                => $row->deleted_at,
                ]);
            });
        }
    }
};
