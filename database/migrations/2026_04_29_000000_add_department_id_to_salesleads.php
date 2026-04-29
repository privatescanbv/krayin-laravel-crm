<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salesleads', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->after('lead_id');
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });

        // Backfill from lead->department_id (subquery form works on both MySQL and SQLite)
        DB::statement('
            UPDATE salesleads
            SET department_id = (
                SELECT department_id FROM leads WHERE leads.id = salesleads.lead_id
            )
            WHERE lead_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::table('salesleads', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
    }
};
