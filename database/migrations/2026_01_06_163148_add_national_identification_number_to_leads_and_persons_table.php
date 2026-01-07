<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('national_identification_number')->nullable()->comment('BSN');
        });

        Schema::table('persons', function (Blueprint $table) {
            $table->string('national_identification_number')->nullable()->comment('BSN');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('national_identification_number');
        });

        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn('national_identification_number');
        });
    }
};
