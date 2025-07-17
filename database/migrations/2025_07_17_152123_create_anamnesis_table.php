<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anamnesis', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 255)->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at')->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->char('created_by', 36)->nullable();
            $table->text('description')->nullable();
            $table->tinyInteger('deleted')->default(0);
            $table->char('team_id', 36)->nullable();
            $table->char('team_set_id', 36)->nullable();
            $table->char('assigned_user_id', 36)->nullable();
            $table->text('comment_clinic')->nullable();
            $table->integer('lengte')->nullable();
            $table->integer('gewicht')->nullable();
            $table->tinyInteger('metalen')->nullable();
            $table->text('opm_metalen_c')->nullable();
            $table->tinyInteger('medicijnen')->nullable();
            $table->text('opm_medicijnen_c')->nullable();
            $table->tinyInteger('glaucoom')->nullable();
            $table->text('opm_glaucoom_c')->nullable();
            $table->tinyInteger('claustrofobie')->nullable();
            $table->tinyInteger('dormicum')->nullable();
            $table->tinyInteger('hart_operatie_c')->nullable();
            $table->text('opm_hart_operatie_c')->nullable();
            $table->tinyInteger('implantaat_c')->nullable();
            $table->text('opm_implantaat_c')->nullable();
            $table->tinyInteger('operaties_c')->nullable();
            $table->text('opm_operaties_c')->nullable();
            $table->string('opmerking', 255)->nullable();
            $table->tinyInteger('hart_erfelijk')->nullable();
            $table->text('opm_erf_hart_c')->nullable();
            $table->tinyInteger('vaat_erfelijk')->nullable();
            $table->text('opm_erf_vaat_c')->nullable();
            $table->tinyInteger('tumoren_erfelijk')->nullable();
            $table->text('opm_erf_tumor_c')->nullable();
            $table->tinyInteger('allergie_c')->nullable();
            $table->text('opm_allergie_c')->nullable();
            $table->tinyInteger('rugklachten')->nullable();
            $table->text('opm_rugklachten_c')->nullable();
            $table->tinyInteger('heart_problems')->nullable();
            $table->text('opm_hartklachten_c')->nullable();
            $table->tinyInteger('smoking')->nullable();
            $table->text('opm_roken_c')->nullable();
            $table->tinyInteger('diabetes')->nullable();
            $table->text('opm_diabetes_c')->nullable();
            $table->tinyInteger('spijsverteringsklachten')->nullable();
            $table->text('opm_spijsvertering_c')->nullable();
            $table->text('risico_hartinfarct')->nullable(); // MultiSelect
            $table->tinyInteger('actief')->nullable();
            $table->text('opm_advies_c')->nullable();
            $table->unsignedInteger('lead_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anamnesis');
    }
};
