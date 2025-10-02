<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This migration is replaced by 2025_01_22_000003_fix_group_id_column_type_in_activities_table.php
        // Skip to avoid conflicts
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['group_id']);
            }
            $table->dropColumn('group_id');
        });
    }
};
