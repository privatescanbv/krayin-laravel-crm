<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_regels', function (Blueprint $table) {
            $table->unsignedInteger('person_id')->nullable()->after('product_id');
            $table->foreign('person_id')->references('id')->on('persons')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('order_regels', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->dropColumn('person_id');
        });
    }
};
