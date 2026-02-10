<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite stores enum as a CHECK constraint, so we need to recreate the column.
        // For MySQL the enum column is altered in place.
        if (DB::getDriverName() === 'sqlite') {
            // Drop the old CHECK-constrained column and re-add with the new set
            Schema::table('lead_pipelines', function (Blueprint $table) {
                $table->dropColumn('type');
            });

            Schema::table('lead_pipelines', function (Blueprint $table) {
                $table->enum('type', ['lead', 'workflow', 'order'])->default('lead')->after('is_default');
            });
        } else {
            DB::statement("ALTER TABLE lead_pipelines MODIFY COLUMN `type` ENUM('lead', 'workflow', 'order') NOT NULL DEFAULT 'lead'");
        }

        // Backfill: any pipeline with an empty type whose name contains "Orders" is an order pipeline.
        DB::table('lead_pipelines')
            ->where('type', '')
            ->where('name', 'like', '%Orders%')
            ->update(['type' => 'order']);

        // Safety net: set remaining empty types to 'lead'
        DB::table('lead_pipelines')
            ->where('type', '')
            ->update(['type' => 'lead']);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('lead_pipelines', function (Blueprint $table) {
                $table->dropColumn('type');
            });

            Schema::table('lead_pipelines', function (Blueprint $table) {
                $table->enum('type', ['lead', 'workflow'])->default('lead')->after('is_default');
            });
        } else {
            DB::statement("ALTER TABLE lead_pipelines MODIFY COLUMN `type` ENUM('lead', 'workflow') NOT NULL DEFAULT 'lead'");
        }
    }
};
