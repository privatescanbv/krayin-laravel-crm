<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->after('sales_lead_id');

            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['order_id']);
            }
            $table->dropColumn('order_id');
        });
    }
};
