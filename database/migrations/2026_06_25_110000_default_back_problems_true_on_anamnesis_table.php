<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anamnesis', function (Blueprint $table) {
            $table->tinyInteger('back_problems')->nullable()->default(1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('anamnesis', function (Blueprint $table) {
            $table->tinyInteger('back_problems')->nullable()->default(null)->change();
        });
    }
};
