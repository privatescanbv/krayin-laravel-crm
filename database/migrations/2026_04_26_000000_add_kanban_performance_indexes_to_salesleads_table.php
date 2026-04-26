<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salesleads', function (Blueprint $table) {
            // Composite index covering the kanban WHERE + ORDER BY in one scan
            $table->index(['pipeline_stage_id', 'created_at'], 'salesleads_kanban_stage_created_idx');
        });

        Schema::table('emails', function (Blueprint $table) {
            // Speeds up withCount for unread emails per sales lead
            $table->index(['sales_lead_id', 'is_read'], 'emails_sales_lead_id_is_read_idx');
        });
    }

    public function down(): void
    {
        Schema::table('salesleads', function (Blueprint $table) {
            $table->dropIndex('salesleads_kanban_stage_created_idx');
        });

        Schema::table('emails', function (Blueprint $table) {
            $table->dropIndex('emails_sales_lead_id_is_read_idx');
        });
    }
};
