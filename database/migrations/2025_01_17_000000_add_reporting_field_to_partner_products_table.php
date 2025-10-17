<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_products', function (Blueprint $table) {
            $table->boolean('reporting')->default(false)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('partner_products', function (Blueprint $table) {
            $table->dropColumn('reporting');
        });
    }
};