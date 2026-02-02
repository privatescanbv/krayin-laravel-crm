<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_notifications', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('patient_id');
            $table->string('type', 50);
            $table->boolean('dismissable')->default(false);

            $table->string('title', 255);
            $table->string('summary', 500)->nullable();

            $table->string('reference_type', 50);
            $table->unsignedBigInteger('reference_id');

            $table->dateTime('read_at')->nullable();
            $table->dateTime('dismissed_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('last_notified_by_email_at')->nullable();

            $table->timestamps();

            AuditTrailMigrationHelper::addAuditTrailColumns($table);

            $table->foreign('patient_id')
                ->references('id')
                ->on('persons')
                ->onDelete('cascade');

            $table->index(['patient_id', 'dismissed_at', 'expires_at'], 'patient_notifications_patient_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_notifications');
    }
};
