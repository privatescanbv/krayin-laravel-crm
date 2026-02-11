<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'diagnoseform_pdf_url')) {
                return;
            }

            // Keep column ordering stable when diagnosis_form_id exists.
            if (Schema::hasColumn('leads', 'diagnosis_form_id')) {
                $table->text('diagnoseform_pdf_url')->nullable()->after('diagnosis_form_id');
            } else {
                $table->text('diagnoseform_pdf_url')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'diagnoseform_pdf_url')) {
                $table->dropColumn('diagnoseform_pdf_url');
            }
        });
    }
};
