<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anamnesis', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->after('sales_id');
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->unique(['order_id', 'person_id'], 'anamnesis_order_person_unique');
        });
    }

    public function down(): void
    {
        Schema::table('anamnesis', function (Blueprint $table) {
            $table->dropUnique('anamnesis_order_person_unique');
            $table->dropForeign(['order_id']);
            $table->dropColumn('order_id');
        });
    }
};
