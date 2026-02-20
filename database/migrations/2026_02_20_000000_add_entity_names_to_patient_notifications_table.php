<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_notifications', function (Blueprint $table) {
            $table->json('entity_names')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('patient_notifications', function (Blueprint $table) {
            $table->dropColumn('entity_names');
        });
    }
};
