<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('lead_persons', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('lead_id')->unsigned();
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');

            $table->integer('person_id')->unsigned();
            $table->foreign('person_id')->references('id')->on('persons')->onDelete('cascade');

            $table->timestamps();

            // Ensure unique combinations
            $table->unique(['lead_id', 'person_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('lead_persons');
    }
};
