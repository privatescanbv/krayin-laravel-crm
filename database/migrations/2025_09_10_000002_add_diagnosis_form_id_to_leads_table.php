<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'diagnosis_form_id')) {
                $table->unsignedInteger('diagnosis_form_id')->nullable()->after('mri_status');
            }
        });

        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'has_diagnosis_form')) {
                $table->dropColumn('has_diagnosis_form');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'diagnosis_form_id')) {
                $table->dropColumn('diagnosis_form_id');
            }
        });

        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'has_diagnosis_form')) {
                $table->boolean('has_diagnosis_form')->default(false)->after('mri_status');
            }
        });
    }
};
