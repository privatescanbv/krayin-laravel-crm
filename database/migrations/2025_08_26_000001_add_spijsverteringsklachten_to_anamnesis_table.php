<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anamnesis', function (Blueprint $table) {
            $table->boolean('spijsverteringsklachten')->default(false)->after('diabetes');
            $table->text('digestive_complaints_notes')->nullable()->after('spijsverteringsklachten');
        });
    }

    public function down(): void
    {
        Schema::table('anamnesis', function (Blueprint $table) {
            $table->dropColumn(['spijsverteringsklachten', 'digestive_complaints_notes']);
        });
    }
};
