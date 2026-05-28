<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('leads', 'department_id_after_won')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['department_id_after_won']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('department_id_after_won', 'order_department_id_after_won');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreign('order_department_id_after_won')->references('id')->on('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('leads', 'order_department_id_after_won')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['order_department_id_after_won']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->renameColumn('order_department_id_after_won', 'department_id_after_won');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreign('department_id_after_won')->references('id')->on('departments')->nullOnDelete();
        });
    }
};
