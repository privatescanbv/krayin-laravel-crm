<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add the separate time-override column
        Schema::table('orders', function (Blueprint $table) {
            $table->string('first_examination_time', 5)->nullable()->after('first_examination_at');
        });

        // 2. Data migration: extract the time component from existing datetime values
        //    into first_examination_time before stripping the time from the column.
        DB::table('orders')
            ->whereNotNull('first_examination_at')
            ->get(['id', 'first_examination_at'])
            ->each(function (object $order) {
                $raw = (string) $order->first_examination_at;
                // Datetime strings are longer than 10 chars (YYYY-MM-DD HH:MM:SS)
                if (strlen($raw) > 10) {
                    $time = substr($raw, 11, 5); // 'HH:MM'
                    if ($time !== '00:00') {
                        DB::table('orders')
                            ->where('id', $order->id)
                            ->update(['first_examination_time' => $time]);
                    }
                }
            });

        // 3. Convert first_examination_at from datetime to date
        Schema::table('orders', function (Blueprint $table) {
            $table->date('first_examination_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dateTime('first_examination_at')->nullable()->change();
            $table->dropColumn('first_examination_time');
        });
    }
};
