<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salesleads', function (Blueprint $table) {
            // users.id is an unsigned INT (Krayin), so no foreignId() (BIGINT).
            $table->unsignedInteger('doctor_user_id')->nullable()->after('user_id')
                ->comment('Behandelend arts (rol Arts); alleen Herniapoli, optioneel');
            $table->foreign('doctor_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('salesleads', function (Blueprint $table) {
            $table->dropForeign(['doctor_user_id']);
            $table->dropColumn('doctor_user_id');
        });
    }
};
