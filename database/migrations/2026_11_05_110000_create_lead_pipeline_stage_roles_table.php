<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Geen rijen voor een stage = zichtbaar voor alle rollen (default).
        // Met rijen: alleen zichtbaar voor de gekoppelde rollen. Ids zijn unsigned INT (Krayin).
        Schema::create('lead_pipeline_stage_roles', function (Blueprint $table) {
            $table->unsignedInteger('lead_pipeline_stage_id');
            $table->unsignedInteger('role_id');

            $table->primary(['lead_pipeline_stage_id', 'role_id']);
            $table->foreign('lead_pipeline_stage_id')->references('id')->on('lead_pipeline_stages')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_pipeline_stage_roles');
    }
};
