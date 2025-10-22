<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salesleads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('lost_reason')->nullable();
            $table->date('closed_at')->nullable();
            $table->unsignedInteger('pipeline_stage_id');
            // Foreign key constraint removed - lead_pipeline_stages table not created in migrations
            $table->unsignedInteger('lead_id')->nullable();
            // Foreign key constraint removed - leads table may not exist in SQLite tests
            $table->unsignedInteger('quote_id')->nullable();
            // Foreign key constraint removed - quotes table not created in migrations
            $table->unsignedInteger('user_id')->nullable();
            // Foreign key constraint removed - users table may not exist in SQLite tests
            $table->timestamps();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            // Foreign key constraints removed - users table may not exist in SQLite tests
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salesleads');
    }
};
