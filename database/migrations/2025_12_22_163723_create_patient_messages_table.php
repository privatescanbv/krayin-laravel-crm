<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('person_id');
            $table->string('sender_type'); // patient | staff | system
            $table->unsignedInteger('sender_id')->nullable(); // user_id when staff
            $table->text('body');
            $table->timestamps();

            $table->foreign('person_id')->references('id')->on('persons')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_messages');
    }
};
