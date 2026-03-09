<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('person_activities');
    }

    public function down(): void
    {
        Schema::create('person_activities', function (Blueprint $table) {
            $table->unsignedBigInteger('activity_id');
            $table->unsignedBigInteger('person_id');
            $table->primary(['activity_id', 'person_id']);
        });
    }
};
