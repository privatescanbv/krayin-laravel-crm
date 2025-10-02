<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anamnesis', function (Blueprint $table) {
            if (Schema::hasColumn('anamnesis', 'assigned_user_id')) {
                $table->dropColumn('assigned_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('anamnesis', function (Blueprint $table) {
            $table->char('assigned_user_id', 36)->nullable();
        });
    }
};
