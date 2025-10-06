<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Add composite index for kanban queries
            $table->index(['lead_pipeline_id', 'lead_pipeline_stage_id', 'user_id'], 'leads_kanban_performance_idx');
            
            // Add index for won/lost stage filtering
            $table->index(['lead_pipeline_stage_id'], 'leads_stage_idx');
            
            // Add index for user filtering
            $table->index(['user_id'], 'leads_user_idx');
            
            // Add index for created_at for sorting
            $table->index(['created_at'], 'leads_created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_kanban_performance_idx');
            $table->dropIndex('leads_stage_idx');
            $table->dropIndex('leads_user_idx');
            $table->dropIndex('leads_created_at_idx');
        });
    }
};