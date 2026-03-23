<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('afb_sent_at')->nullable()->after('first_examination_at');
            $table->string('afb_sent_type', 20)->nullable()->after('afb_sent_at');
            $table->unsignedBigInteger('afb_sent_to_clinic_id')->nullable()->after('afb_sent_type');

            $table->foreign('afb_sent_to_clinic_id')
                ->references('id')
                ->on('clinics')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['afb_sent_to_clinic_id']);
            $table->dropColumn([
                'afb_sent_at',
                'afb_sent_type',
                'afb_sent_to_clinic_id',
            ]);
        });
    }
};
