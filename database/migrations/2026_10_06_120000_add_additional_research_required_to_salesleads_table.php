<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salesleads', function (Blueprint $table) {
            $table->boolean('additional_research_required')->default(false)->after('assessment_outcome')
                ->comment('Herniapoli: aanvullend onderzoek vereist; alleen ja t/m "Gepland voor aanvullend onderzoek"');
        });
    }

    public function down(): void
    {
        Schema::table('salesleads', function (Blueprint $table) {
            $table->dropColumn('additional_research_required');
        });
    }
};
