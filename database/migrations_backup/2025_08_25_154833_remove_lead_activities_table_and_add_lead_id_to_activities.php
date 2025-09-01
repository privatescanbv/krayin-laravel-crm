<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the lead_activities pivot table
        Schema::dropIfExists('lead_activities');

        // Add lead_id to activities table
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedInteger('lead_id')->nullable()->after('group_id');
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('set null');
        });
    }

    public function down(): void
    {
        // Remove lead_id from activities table
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
            $table->dropColumn('lead_id');
        });

        // Recreate the lead_activities pivot table
        Schema::create('lead_activities', function (Blueprint $table) {
            $table->integer('activity_id')->unsigned();
            $table->foreign('activity_id')->references('id')->on('activities')->onDelete('cascade');

            $table->integer('lead_id')->unsigned();
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');
        });
    }
};
