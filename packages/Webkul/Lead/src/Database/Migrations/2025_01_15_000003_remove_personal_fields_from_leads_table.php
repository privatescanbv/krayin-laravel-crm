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
        Schema::table('leads', function (Blueprint $table) {
            // Remove personal fields that now belong to persons
            $table->dropColumn([
                'salutation',
                'first_name',
                'last_name',
                'lastname_prefix',
                'married_name',
                'married_name_prefix',
                'initials',
                'date_of_birth',
                'gender',
                'emails',
                'phones'
            ]);
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
            // Add back personal fields
            $table->string('salutation')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('lastname_prefix')->nullable();
            $table->string('married_name')->nullable();
            $table->string('married_name_prefix')->nullable();
            $table->string('initials')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->json('emails')->nullable();
            $table->json('phones')->nullable();
        });
    }
};