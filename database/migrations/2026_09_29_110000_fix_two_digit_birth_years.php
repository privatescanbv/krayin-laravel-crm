<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Legacy records were stored with a two-digit year (e.g. 0072-03-26 instead of 1972-03-26).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['persons', 'leads'] as $table) {
            DB::table($table)
                ->whereNotNull('date_of_birth')
                ->where('date_of_birth', '<', '0100-01-01')
                ->get(['id', 'date_of_birth'])
                ->each(fn ($row) => DB::table($table)->where('id', $row->id)->update([
                    'date_of_birth' => Carbon::parse($row->date_of_birth)->addYears(1900)->toDateString(),
                ]));
        }
    }

    public function down(): void
    {
        // Intentionally empty: the original two-digit years were invalid.
    }
};
