<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OrderNumberGenerator
{
    public function next(?int $year = null): string
    {
        $year = $year ?: (int) now()->format('Y');

        return DB::transaction(function () use ($year): string {
            // Ensure the sequence row exists, even under concurrency.
            DB::table('order_number_sequences')->insertOrIgnore([
                'year'        => $year,
                'last_number' => 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            $sequence = DB::table('order_number_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $lastNumber = (int) ($sequence?->last_number ?? 0);

            // Guard against sequences that drifted behind due to direct inserts (e.g. imports).
            $maxInOrders = DB::table('orders')
                ->where('order_number', 'like', $year.'%')
                ->max(DB::raw('CAST(order_number AS UNSIGNED)'));

            if ($maxInOrders) {
                $maxFromOrders = (int) $maxInOrders - ($year * 100000);
                $lastNumber = max($lastNumber, $maxFromOrders);
            }

            $next = $lastNumber + 1;

            DB::table('order_number_sequences')
                ->where('year', $year)
                ->update([
                    'last_number' => $next,
                    'updated_at'  => now(),
                ]);

            return sprintf('%d%05d', $year, $next);
        });
    }
}
