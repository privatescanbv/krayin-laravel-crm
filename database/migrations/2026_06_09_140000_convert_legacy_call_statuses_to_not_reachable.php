<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_STATUSES = ['wordt_teruggebeld', 'afspraak_gemaakt'];

    private const TARGET_STATUS = 'not_reachable';

    public function up(): void
    {
        if (Schema::hasTable('activity_actions')) {
            DB::table('activity_actions')
                ->whereIn('call_status', self::LEGACY_STATUSES)
                ->update(['call_status' => self::TARGET_STATUS]);
        }

        if (Schema::hasTable('call_statuses')) {
            DB::table('call_statuses')
                ->whereIn('status', self::LEGACY_STATUSES)
                ->update(['status' => self::TARGET_STATUS]);
        }
    }

    public function down(): void
    {
        // Legacy status values cannot be restored reliably.
    }
};
