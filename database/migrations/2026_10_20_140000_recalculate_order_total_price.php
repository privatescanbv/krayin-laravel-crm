<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $lostStageIds = DB::table('lead_pipeline_stages')->where('is_lost', true)->pluck('id')->implode(',') ?: '0';

        $lostLines = "(SELECT COALESCE(SUM(oi.total_price), 0) FROM order_items oi WHERE oi.order_id = orders.id AND oi.status = 'lost')";
        $activeLines = "(SELECT COALESCE(SUM(oi.total_price), 0) FROM order_items oi WHERE oi.order_id = orders.id AND oi.status NOT IN ('lost', 'incorrect'))";
        $newTotal = "CASE WHEN pipeline_stage_id IN ({$lostStageIds}) THEN {$lostLines} ELSE {$activeLines} END";

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
