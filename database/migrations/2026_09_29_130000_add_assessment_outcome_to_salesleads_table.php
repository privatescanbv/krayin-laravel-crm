<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salesleads', function (Blueprint $table) {
            $table->string('assessment_outcome', 50)->nullable()->after('lost_reason')
                ->comment('App\Enums\AssessmentOutcome — Herniapoli uitkomst beoordeling');
        });
    }

    public function down(): void
    {
        Schema::table('salesleads', function (Blueprint $table) {
            $table->dropColumn('assessment_outcome');
        });
    }
};
