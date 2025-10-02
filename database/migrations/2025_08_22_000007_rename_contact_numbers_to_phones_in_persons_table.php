<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Then drop the contact_numbers column
        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn('contact_numbers');
        });
    }

    public function down()
    {
        Schema::table('persons', function (Blueprint $table) {
            // Re-add contact_numbers column
            $table->json('contact_numbers')->nullable()->after('phones');
        });
    }
};
