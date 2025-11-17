<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add gvl_form_link column to anamnesis table
        Schema::table('anamnesis', function (Blueprint $table) {
            $table->string('gvl_form_link')->nullable()->after('person_id');
        });

        // Remove gvl_form_link column from salesleads table
        Schema::table('salesleads', function (Blueprint $table) {
            $table->dropColumn('gvl_form_link');
        });
    }

    public function down(): void
    {
        // Add gvl_form_link column back to salesleads table
        Schema::table('salesleads', function (Blueprint $table) {
            $table->string('gvl_form_link')->nullable()->after('contact_person_id');
        });

        // Migrate data back from anamnesis to salesleads
        // For each anamnesis with gvl_form_link, find the saleslead via person
        DB::statement("
            UPDATE salesleads sl
            INNER JOIN saleslead_persons sp ON sl.id = sp.saleslead_id
            INNER JOIN anamnesis a ON sp.person_id = a.person_id
            SET sl.gvl_form_link = a.gvl_form_link
            WHERE a.gvl_form_link IS NOT NULL
            AND a.gvl_form_link != ''
            AND sl.gvl_form_link IS NULL
        ");

        // Remove gvl_form_link column from anamnesis table
        Schema::table('anamnesis', function (Blueprint $table) {
            $table->dropColumn('gvl_form_link');
        });
    }
};
