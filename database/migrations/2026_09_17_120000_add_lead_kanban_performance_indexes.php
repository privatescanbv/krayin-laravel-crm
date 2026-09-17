<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->index(['lead_id', 'is_done'], 'activities_lead_id_is_done_idx');
        });

        Schema::table('emails', function (Blueprint $table) {
            $table->index(['lead_id', 'is_read'], 'emails_lead_id_is_read_idx');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->index(
                ['lead_pipeline_id', 'lead_pipeline_stage_id', 'created_at'],
                'leads_kanban_stage_created_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex('activities_lead_id_is_done_idx');
        });

        Schema::table('emails', function (Blueprint $table) {
            $table->dropIndex('emails_lead_id_is_read_idx');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_kanban_stage_created_idx');
        });
    }
};
