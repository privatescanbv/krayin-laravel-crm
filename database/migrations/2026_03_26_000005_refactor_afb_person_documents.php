<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('afb_dispatch_orders', function (Blueprint $table) {
            $table->dropIndex(['clinic_id', 'order_id']);
            $table->dropForeign(['clinic_id']);
            $table->dropForeign(['clinic_department_id']);
            $table->dropColumn(['clinic_id', 'clinic_department_id']);
        });

        Schema::rename('afb_dispatch_orders', 'afb_person_documents');

        Schema::table('afb_dispatches', function (Blueprint $table) {
            $table->dropColumn('order_ids');
        });
    }

    public function down(): void
    {
        Schema::table('afb_dispatches', function (Blueprint $table) {
            $table->json('order_ids')->nullable()->after('status');
        });

        Schema::rename('afb_person_documents', 'afb_dispatch_orders');

        $driver = Schema::getConnection()->getDriverName();

        Schema::table('afb_dispatch_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('clinic_id')->nullable()->after('order_id');
            $table->unsignedBigInteger('clinic_department_id')->nullable()->after('clinic_id');
        });

        if ($driver !== 'sqlite') {
            Schema::table('afb_dispatch_orders', function (Blueprint $table) {
                $table->foreign('clinic_id')->references('id')->on('clinics')->cascadeOnDelete();
                $table->foreign('clinic_department_id')->references('id')->on('clinic_departments')->nullOnDelete();
            });
        }
    }
};
