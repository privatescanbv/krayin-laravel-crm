<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $lostStageIds = DB::table('lead_pipeline_stages')->where('is_lost', true)->pluck('id')->implode(',') ?: '0';

        $allLines = '(SELECT COALESCE(SUM(oi.total_price), 0) FROM order_items oi WHERE oi.order_id = orders.id)';
        $activeLines = "(SELECT COALESCE(SUM(oi.total_price), 0) FROM order_items oi WHERE oi.order_id = orders.id AND oi.status <> 'lost')";
        $hasActiveLine = "EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = orders.id AND oi.status <> 'lost')";
        $newTotal = "CASE WHEN pipeline_stage_id IN ({$lostStageIds}) OR NOT {$hasActiveLine} THEN {$allLines} ELSE {$activeLines} END";

        DB::table('orders')
            ->whereExists(fn ($q) => $q->from('order_items')->whereColumn('order_items.order_id', 'orders.id'))
            ->whereRaw("total_price <> {$newTotal}")
            ->update(['total_price' => DB::raw($newTotal)]);
    }

    public function down(): void
    {
        //
    }
};
