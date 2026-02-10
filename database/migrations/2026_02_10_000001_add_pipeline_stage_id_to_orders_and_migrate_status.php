<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add pipeline_stage_id column
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('pipeline_stage_id')->nullable()->after('status');
            $table->foreign('pipeline_stage_id')->references('id')->on('lead_pipeline_stages')->nullOnDelete();
        });

        // 3. Drop status column
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status')->nullable()->after('total_price');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['pipeline_stage_id']);
            $table->dropColumn('pipeline_stage_id');
        });
    }
};
