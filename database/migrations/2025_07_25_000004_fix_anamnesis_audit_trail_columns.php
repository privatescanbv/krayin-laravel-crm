<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anamnesis', function (Blueprint $table) {
            // Drop the existing char(36) columns
            $table->dropColumn(['created_by', 'updated_by']);
        });

        Schema::table('anamnesis', function (Blueprint $table) {
            // Add the correct audit trail columns as unsignedInteger
            AuditTrailMigrationHelper::addAuditTrailColumns($table);
        });
    }

    public function down(): void
    {
        Schema::table('anamnesis', function (Blueprint $table) {
            // Drop the audit trail columns
            AuditTrailMigrationHelper::dropAuditTrailColumns($table);
        });

        Schema::table('anamnesis', function (Blueprint $table) {
            // Restore the original char(36) columns
            $table->char('created_by', 36)->nullable();
            $table->char('updated_by', 36)->nullable();
        });
    }
};
