<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('afb_dispatches', function (Blueprint $table) {
            $table->unsignedBigInteger('clinic_department_id')->nullable()->after('clinic_id');
            $table->foreign('clinic_department_id')->references('id')->on('clinic_departments')->nullOnDelete();
        });

        Schema::table('afb_dispatch_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('clinic_department_id')->nullable()->after('clinic_id');
            $table->foreign('clinic_department_id')->references('id')->on('clinic_departments')->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('afb_sent_to_clinic_department_id')->nullable()->after('afb_sent_to_clinic_id');
            $table->foreign('afb_sent_to_clinic_department_id')->references('id')->on('clinic_departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('afb_dispatches', function (Blueprint $table) {
            $table->dropForeign(['clinic_department_id']);
            $table->dropColumn('clinic_department_id');
        });

        Schema::table('afb_dispatch_orders', function (Blueprint $table) {
            $table->dropForeign(['clinic_department_id']);
            $table->dropColumn('clinic_department_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['afb_sent_to_clinic_department_id']);
            $table->dropColumn('afb_sent_to_clinic_department_id');
        });
    }
};
