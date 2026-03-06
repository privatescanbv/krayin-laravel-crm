<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'order_number')) {
                $table->string('order_number', 9)->nullable()->after('id');
            }
        });

        // Backfill existing orders.
        // We assign per-year counters, formatted as YYYY + 5 digits (e.g. 202600001).
        $countersByYear = [];

        DB::table('orders')
            ->select(['id', 'created_at'])
            ->whereNull('order_number')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$countersByYear) {
                foreach ($rows as $row) {
                    $year = null;

                    if (! empty($row->created_at)) {
                        try {
                            $year = Carbon::parse($row->created_at)->year;
                        } catch (Throwable) {
                            $year = null;
                        }
                    }

                    $year = $year ?: (int) now()->format('Y');

                    $next = ($countersByYear[$year] ?? 0) + 1;
                    $countersByYear[$year] = $next;

                    $orderNumber = sprintf('%d%05d', $year, $next);

                    DB::table('orders')
                        ->where('id', $row->id)
                        ->update(['order_number' => $orderNumber]);
                }
            });

        // Ensure sequence table is aligned with the backfilled max per year.
        if (! empty($countersByYear) && Schema::hasTable('order_number_sequences')) {
            foreach ($countersByYear as $year => $lastNumber) {
                DB::table('order_number_sequences')->updateOrInsert(
                    ['year' => (int) $year],
                    ['last_number' => (int) $lastNumber, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_number', 9)->nullable(false)->change();
            $table->unique('order_number');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'order_number')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['order_number']);
            $table->dropColumn('order_number');
        });
    }
};
