<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            // First drop the existing foreign key constraint if it exists
            if (DB::getDriverName() !== 'sqlite') {
                try {
                    $table->dropForeign(['group_id']);
                } catch (Exception $e) {
                    // Ignore if constraint doesn't exist
                }
            }
            
            // Drop the column if it exists
            if (Schema::hasColumn('activities', 'group_id')) {
                $table->dropColumn('group_id');
            }
        });

        Schema::table('activities', function (Blueprint $table) {
            // Add the column with correct type
            $table->unsignedBigInteger('group_id')->nullable();
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('set null');
        });
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