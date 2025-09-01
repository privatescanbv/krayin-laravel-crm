<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('lead_value');
        });
    }

    public function down()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->decimal('lead_value', 12, 4)->nullable();
        });
    }
};
