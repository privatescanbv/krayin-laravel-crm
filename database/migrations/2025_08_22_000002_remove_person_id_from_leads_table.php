<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->dropColumn('person_id');
        });
    }

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
