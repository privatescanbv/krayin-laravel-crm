<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salesleads', function (Blueprint $table) {
            $table->index('pipeline_stage_id');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->index('sales_lead_id');
        });
    }

    public function down(): void
    {
        Schema::table('salesleads', function (Blueprint $table) {
            $table->dropIndex(['pipeline_stage_id']);
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex(['sales_lead_id']);
        });
    }
};
