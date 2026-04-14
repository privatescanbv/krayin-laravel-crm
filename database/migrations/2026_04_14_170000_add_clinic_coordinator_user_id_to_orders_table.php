<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('orders', 'clinic_coordinator_user_id')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('clinic_coordinator_user_id')->nullable()->after('user_id');

            $table->foreign('clinic_coordinator_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['clinic_coordinator_user_id']);
            $table->dropColumn('clinic_coordinator_user_id');
        });
    }
};
