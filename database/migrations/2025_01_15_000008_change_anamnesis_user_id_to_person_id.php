<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // SQLite doesn't support dropping foreign keys, so we recreate the table
        Schema::dropIfExists('anamnesis_backup');
        
        // Create backup table with new structure
        Schema::create('anamnesis_backup', function (Blueprint $table) {
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
            $table->integer('height')->nullable();
            $table->integer('weight')->nullable();
            $table->tinyInteger('metals')->nullable();
            $table->text('metals_notes')->nullable();
            $table->tinyInteger('medications')->nullable();
            $table->text('medications_notes')->nullable();
            $table->tinyInteger('glaucoma')->nullable();
            $table->text('glaucoma_notes')->nullable();
            $table->tinyInteger('claustrophobia')->nullable();
            $table->tinyInteger('dormicum')->nullable();
            $table->tinyInteger('heart_surgery')->nullable();
            $table->text('heart_surgery_notes')->nullable();
            $table->tinyInteger('implant')->nullable();
            $table->text('implant_notes')->nullable();
            $table->tinyInteger('surgeries')->nullable();
            $table->text('surgeries_notes')->nullable();
            $table->string('remarks', 255)->nullable();
            $table->tinyInteger('hereditary_heart')->nullable();
            $table->text('hereditary_heart_notes')->nullable();
            $table->tinyInteger('hereditary_vascular')->nullable();
            $table->text('hereditary_vascular_notes')->nullable();
            $table->tinyInteger('hereditary_tumors')->nullable();
            $table->text('hereditary_tumors_notes')->nullable();
            $table->tinyInteger('allergies')->nullable();
            $table->text('allergies_notes')->nullable();
            $table->tinyInteger('back_problems')->nullable();
            $table->text('back_problems_notes')->nullable();
            $table->tinyInteger('heart_problems')->nullable();
            $table->text('heart_problems_notes')->nullable();
            $table->tinyInteger('smoking')->nullable();
            $table->text('smoking_notes')->nullable();
            $table->tinyInteger('diabetes')->nullable();
            $table->text('diabetes_notes')->nullable();
            $table->tinyInteger('digestive_problems')->nullable();
            $table->text('digestive_problems_notes')->nullable();
            $table->text('heart_attack_risk')->nullable();
            $table->tinyInteger('active')->nullable();
            $table->text('advice_notes')->nullable();
            $table->unsignedInteger('lead_id')->nullable();
            $table->unsignedInteger('person_id')->nullable(); // Changed from user_id
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('set null');
            $table->foreign('person_id')->references('id')->on('persons')->onDelete('set null');
        });
        
        // Copy data from old table to new table (ignore user_id since we can't map it to person_id)
        \DB::statement('INSERT INTO anamnesis_backup SELECT 
            id, name, created_at, updated_at, updated_by, created_by, description, deleted,
            team_id, team_set_id, assigned_user_id, comment_clinic, height, weight,
            metals, metals_notes, medications, medications_notes, glaucoma, glaucoma_notes,
            claustrophobia, dormicum, heart_surgery, heart_surgery_notes, implant, implant_notes,
            surgeries, surgeries_notes, remarks, hereditary_heart, hereditary_heart_notes,
            hereditary_vascular, hereditary_vascular_notes, hereditary_tumors, hereditary_tumors_notes,
            allergies, allergies_notes, back_problems, back_problems_notes, heart_problems, heart_problems_notes,
            smoking, smoking_notes, diabetes, diabetes_notes, digestive_problems, digestive_problems_notes,
            heart_attack_risk, active, advice_notes, lead_id, NULL as person_id
            FROM anamnesis');
        
        // Drop old table and rename backup
        Schema::dropIfExists('anamnesis');
        Schema::rename('anamnesis_backup', 'anamnesis');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('anamnesis', function (Blueprint $table) {
            // Drop new foreign key and column
            $table->dropForeign(['person_id']);
            $table->dropColumn('person_id');
            
            // Restore old user_id column
            $table->unsignedInteger('user_id')->nullable()->after('lead_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }
};