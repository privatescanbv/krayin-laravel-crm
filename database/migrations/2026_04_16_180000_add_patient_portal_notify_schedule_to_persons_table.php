<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->timestamp('patient_portal_notify_scheduled_at')->nullable()->after('onboarding_completed_at');
            $table->timestamp('patient_portal_last_notify_email_at')->nullable()->after('patient_portal_notify_scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn([
                'patient_portal_notify_scheduled_at',
                'patient_portal_last_notify_email_at',
            ]);
        });
    }
};
