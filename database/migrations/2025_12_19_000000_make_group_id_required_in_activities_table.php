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
        Schema::table('activities', function (Blueprint $table) {
            // First drop the existing foreign key constraint
            $table->dropForeign(['group_id']);
            
            // Make the column NOT NULL
            $table->unsignedInteger('group_id')->nullable(false)->change();
            
            // Re-add the foreign key constraint with CASCADE instead of SET NULL
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
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
            // Drop the foreign key constraint
            $table->dropForeign(['group_id']);
            
            // Make the column nullable again
            $table->unsignedInteger('group_id')->nullable()->change();
            
            // Re-add the original foreign key constraint with SET NULL
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('set null');
        });
    }
};