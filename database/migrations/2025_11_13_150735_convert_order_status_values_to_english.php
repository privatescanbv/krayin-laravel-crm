<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Convert order statuses
        DB::table('orders')
            ->where('status', 'nieuw')
            ->update(['status' => 'new']);

        DB::table('orders')
            ->where('status', 'ingepland')
            ->update(['status' => 'planned']);

        DB::table('orders')
            ->where('status', 'verstuurd')
            ->update(['status' => 'sent']);

        DB::table('orders')
            ->where('status', 'akkoord')
            ->update(['status' => 'approved']);

        DB::table('orders')
            ->where('status', 'afgewezen')
            ->update(['status' => 'rejected']);

        // Convert order item statuses
        DB::table('order_items')
            ->where('status', 'nieuw')
            ->update(['status' => 'new']);

        DB::table('order_items')
            ->where('status', 'ingepland')
            ->update(['status' => 'planned']);

        // Convert removed status "moet_worden_ingepland" to "new"
        DB::table('order_items')
            ->where('status', 'moet_worden_ingepland')
            ->update(['status' => 'new']);
    }

    public function down(): void
    {
        // Convert order statuses back
        DB::table('orders')
            ->where('status', 'new')
            ->update(['status' => 'nieuw']);

        DB::table('orders')
            ->where('status', 'planned')
            ->update(['status' => 'ingepland']);

        DB::table('orders')
            ->where('status', 'sent')
            ->update(['status' => 'verstuurd']);

        DB::table('orders')
            ->where('status', 'approved')
            ->update(['status' => 'akkoord']);

        DB::table('orders')
            ->where('status', 'rejected')
            ->update(['status' => 'afgewezen']);

        // Convert order item statuses back
        DB::table('order_items')
            ->where('status', 'new')
            ->update(['status' => 'nieuw']);

        DB::table('order_items')
            ->where('status', 'planned')
            ->update(['status' => 'ingepland']);

        // Note: "moet_worden_ingepland" cannot be restored as we don't know
        // which "new" items originally had this status
    }
};
