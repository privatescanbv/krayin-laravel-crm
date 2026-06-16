<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_prices', function (Blueprint $table) {
            $table->boolean('force_received')->default(false)->after('purchase_price');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_prices', function (Blueprint $table) {
            $table->dropColumn('force_received');
        });
    }
};
