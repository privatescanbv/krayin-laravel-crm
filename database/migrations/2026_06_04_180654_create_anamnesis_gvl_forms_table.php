<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anamnesis_gvl_forms', function (Blueprint $table) {
            $table->id();
            $table->char('anamnesis_id', 36);
            $table->foreign('anamnesis_id')->references('id')->on('anamnesis')->cascadeOnDelete();
            $table->string('gvl_form_id')->nullable();
            $table->string('gvl_form_status')->nullable();
            $table->string('gvl_form_type')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
            $table->index('anamnesis_id');
            $table->index('gvl_form_id');
        });

        // Migrate existing data: copy current gvl_form_id rows from anamnesis to new table
        DB::statement('
            INSERT INTO anamnesis_gvl_forms
                (anamnesis_id, gvl_form_id, gvl_form_status, gvl_form_type, created_at, updated_at)
            SELECT id, gvl_form_id, gvl_form_status, gvl_form_type, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM anamnesis
            WHERE gvl_form_id IS NOT NULL
        ');

        // Drop the now-redundant columns from the anamnesis table
        Schema::table('anamnesis', function (Blueprint $table) {
            $table->dropColumn(['gvl_form_id', 'gvl_form_status', 'gvl_form_type']);
        });
    }

    public function down(): void
    {
        // Restore columns on anamnesis table
        Schema::table('anamnesis', function (Blueprint $table) {
            $table->string('gvl_form_id')->nullable()->after('person_id');
            $table->string('gvl_form_status')->nullable()->after('gvl_form_id');
            $table->string('gvl_form_type')->nullable()->after('gvl_form_status');
        });

        // Restore most recent form per anamnesis back to the anamnesis columns
        DB::statement('
            UPDATE anamnesis a
            INNER JOIN (
                SELECT anamnesis_id, gvl_form_id, gvl_form_status, gvl_form_type
                FROM anamnesis_gvl_forms
                WHERE id IN (
                    SELECT MAX(id) FROM anamnesis_gvl_forms GROUP BY anamnesis_id
                )
            ) latest ON latest.anamnesis_id = a.id
            SET a.gvl_form_id = latest.gvl_form_id,
                a.gvl_form_status = latest.gvl_form_status,
                a.gvl_form_type = latest.gvl_form_type
        ');

        Schema::dropIfExists('anamnesis_gvl_forms');
    }
};
