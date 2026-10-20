<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inkoop_invoices', function (Blueprint $table) {
            $table->json('parse_warnings')->nullable()->after('parser');
        });
    }

    public function down(): void
    {
        Schema::table('inkoop_invoices', function (Blueprint $table) {
            $table->dropColumn('parse_warnings');
        });
    }
};
