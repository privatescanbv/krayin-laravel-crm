<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anamnesis', function (Blueprint $table) {
            $table->boolean('infectious_disease')->nullable()->after('diabetes_notes');
            $table->text('infectious_disease_notes')->nullable()->after('infectious_disease');
        });

        if (Schema::hasColumn('anamnesis', 'advice_notes')) {
            Schema::table('anamnesis', function (Blueprint $table) {
                $table->dropColumn('advice_notes');
            });
        }
    }

    public function down(): void
    {
        Schema::table('anamnesis', function (Blueprint $table) {
            $table->text('advice_notes')->nullable();
            $table->dropColumn(['infectious_disease', 'infectious_disease_notes']);
        });
    }
};
