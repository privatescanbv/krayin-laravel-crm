<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_products', function (Blueprint $table) {
            $table->dropUnique(['partner_name']);
            $table->dropColumn('partner_name');
        });
    }

    public function down(): void
    {
        Schema::table('partner_products', function (Blueprint $table) {
            $table->string('partner_name')->after('resource_type_id');
            $table->unique('partner_name');
        });
    }
};