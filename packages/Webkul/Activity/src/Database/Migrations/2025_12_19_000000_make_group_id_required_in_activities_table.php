<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // First, ensure all existing activities have a group_id
        // For activities without group_id, try to assign based on lead's department or user's group
        DB::statement("
            UPDATE activities 
            SET group_id = (
                SELECT g.id 
                FROM groups g 
                INNER JOIN leads l ON l.department_id IS NOT NULL
                INNER JOIN departments d ON d.id = l.department_id
                WHERE l.id = activities.lead_id 
                AND g.name = d.name
                LIMIT 1
            )
            WHERE group_id IS NULL 
            AND lead_id IS NOT NULL
        ");

        // For remaining activities without group_id, assign based on user's first group
        DB::statement("
            UPDATE activities 
            SET group_id = (
                SELECT ug.group_id 
                FROM user_groups ug 
                WHERE ug.user_id = activities.user_id 
                LIMIT 1
            )
            WHERE group_id IS NULL 
            AND user_id IS NOT NULL
        ");

        // For any remaining activities without group_id, assign to the first available group
        DB::statement("
            UPDATE activities 
            SET group_id = (
                SELECT id FROM groups ORDER BY id LIMIT 1
            )
            WHERE group_id IS NULL
        ");

        // Now make the column NOT NULL
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedInteger('group_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedInteger('group_id')->nullable()->change();
        });
    }
};