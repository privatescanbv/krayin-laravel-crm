<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anamnesis_gvl_forms', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('gvl_form_status');
        });

        // Backfill: forms already completed get their last-updated moment as a best guess.
        DB::table('anamnesis_gvl_forms')
            ->where('gvl_form_status', 'completed')
            ->whereNull('completed_at')
            ->update(['completed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('anamnesis_gvl_forms', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
