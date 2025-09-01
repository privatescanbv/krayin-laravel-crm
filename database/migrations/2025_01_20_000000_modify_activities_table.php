<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            // Add assigned_at (2025_01_20_000001)
            $table->timestamp('assigned_at')->nullable();

            // Add group_id (2025_06_18_000000)
            $table->unsignedInteger('group_id')->nullable();
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('set null');

            // Add lead_id (2025_08_25_154833)
            $table->unsignedInteger('lead_id')->nullable();
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            // Remove added columns
            $table->dropForeign(['lead_id']);
            $table->dropColumn('lead_id');
            
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
            
            $table->dropColumn('assigned_at');
        });
    }
};