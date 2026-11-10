<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Choice from the "won" modal: also create an order next to the new sales (default: yes).
            $table->boolean('create_order_after_won')->default(true)->after('order_department_id_after_won');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('create_order_after_won');
        });
    }
};
