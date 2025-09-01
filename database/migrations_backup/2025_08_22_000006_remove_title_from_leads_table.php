<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('leads', 'title')) {
            Schema::table('leads', function (Blueprint $table) {
                // Remove title column
                $table->dropColumn('title');
            });
        }
    }

    public function down()
    {
        Schema::table('leads', function (Blueprint $table) {
            // Add title column back
            $table->string('title')->after('id');
        });
    }
};
