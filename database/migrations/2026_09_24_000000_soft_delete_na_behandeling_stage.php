<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Hernia sales stage 'Na-behandeling' (sales-opname-hernia); its leads move to '1e Nazorg'.
    private const NA_BEHANDELING_STAGE_ID = 24;

    private const EERSTE_NAZORG_STAGE_ID = 25;

    public function up(): void
    {
        Schema::table('lead_pipeline_stages', function (Blueprint $table) {
            $table->softDeletes();
        });

        DB::table('salesleads')
            ->where('pipeline_stage_id', self::NA_BEHANDELING_STAGE_ID)
            ->update(['pipeline_stage_id' => self::EERSTE_NAZORG_STAGE_ID]);

        DB::table('lead_pipeline_stages')
            ->where('id', self::NA_BEHANDELING_STAGE_ID)
            ->update(['deleted_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('lead_pipeline_stages', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
