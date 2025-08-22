<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('anamnesis', function (Blueprint $table) {
            // Drop old foreign key and column
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            
            // Add new person_id column with foreign key
            $table->unsignedInteger('person_id')->nullable()->after('lead_id');
            $table->foreign('person_id')->references('id')->on('persons')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('anamnesis', function (Blueprint $table) {
            // Drop new foreign key and column
            $table->dropForeign(['person_id']);
            $table->dropColumn('person_id');
            
            // Restore old user_id column
            $table->unsignedInteger('user_id')->nullable()->after('lead_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }
};