<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // User-editable display order within an order; duplicates allowed (ties sort by id).
            $table->unsignedInteger('sort_order')->default(0)->after('order_id');
        });

        // Number existing lines 1, 2, 3, … per order in id order, so current display order is preserved.
        if (DB::getDriverName() === 'mysql') {
            // Derived table is materialized, so MySQL allows updating order_items from it (avoids error 1093).
            DB::statement('
                UPDATE order_items oi
                JOIN (SELECT id, ROW_NUMBER() OVER (PARTITION BY order_id ORDER BY id) AS rn FROM order_items) numbered
                    ON numbered.id = oi.id
                SET oi.sort_order = numbered.rn
            ');

            return;
        }

        $counters = [];

        DB::table('order_items')
            ->select('id', 'order_id')
            ->lazyById()
            ->each(function ($row) use (&$counters) {
                $counters[$row->order_id] = ($counters[$row->order_id] ?? 0) + 1;

                DB::table('order_items')->where('id', $row->id)->update(['sort_order' => $counters[$row->order_id]]);
            });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
