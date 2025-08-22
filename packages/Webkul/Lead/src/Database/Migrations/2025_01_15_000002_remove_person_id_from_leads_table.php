<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('leads', function (Blueprint $table) {
            // SQLite doesn't support dropping foreign keys
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['person_id']);
            }
            
            $table->dropColumn('person_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->integer('person_id')->unsigned()->nullable();
            
            $table->foreign('person_id')
                ->references('id')->on('persons')
                ->onDelete('restrict');
        });
    }
};