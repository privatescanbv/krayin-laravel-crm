<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->boolean('auto_created')->default(false)->after('currency');
        });

        // Backfill: open refunds created by OrderRefundService, recognised by their "Klant terugbetalen"
        // task created in the same request (same order, within a few seconds).
        $tasks = DB::table('activities')
            ->where('title', 'Klant terugbetalen')
            ->where('comment', 'like', 'Automatisch aangemaakt%')
            ->whereNotNull('order_id')
            ->get(['order_id', 'created_at']);

        foreach ($tasks as $task) {
            $at = strtotime($task->created_at);

            DB::table('order_payments')
                ->where('order_id', $task->order_id)
                ->where('type', 'refund')
                ->whereBetween('created_at', [date('Y-m-d H:i:s', $at - 5), date('Y-m-d H:i:s', $at + 5)])
                ->update(['auto_created' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropColumn('auto_created');
        });
    }
};
