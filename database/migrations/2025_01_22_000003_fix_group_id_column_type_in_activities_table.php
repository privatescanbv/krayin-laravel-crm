<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if column already exists with correct type
        if (Schema::hasColumn('activities', 'group_id')) {
            // Column exists, check if we need to modify it
            $columnType = DB::select("SHOW COLUMNS FROM activities WHERE Field = 'group_id'")[0]->Type ?? '';

            if (strpos($columnType, 'bigint') === false) {
                // Wrong type, need to recreate
                Schema::table('activities', function (Blueprint $table) {
                    // Drop foreign key if it exists
                    $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activities' AND COLUMN_NAME = 'group_id' AND REFERENCED_TABLE_NAME IS NOT NULL");

                    if (! empty($foreignKeys)) {
                        $table->dropForeign(['group_id']);
                    }

                    $table->dropColumn('group_id');
                });

                Schema::table('activities', function (Blueprint $table) {
                    $table->unsignedInteger('group_id')->nullable();
                    $table->foreign('group_id')->references('id')->on('groups')->onDelete('set null');
                });
            }
        } else {
            // Column doesn't exist, create it
            Schema::table('activities', function (Blueprint $table) {
                $table->unsignedInteger('group_id')->nullable();
                $table->foreign('group_id')->references('id')->on('groups')->onDelete('set null');
            });
        }
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
