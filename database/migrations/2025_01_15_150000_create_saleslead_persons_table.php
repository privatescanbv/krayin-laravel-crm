<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('saleslead_persons', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('saleslead_id')->unsigned();
            $table->foreign('saleslead_id')->references('id')->on('salesleads')->onDelete('cascade');

            $table->integer('person_id')->unsigned();
            $table->foreign('person_id')->references('id')->on('persons')->onDelete('cascade');

            $table->timestamps();

            // Ensure unique combinations
            $table->unique(['saleslead_id', 'person_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('saleslead_persons');
    }
};
