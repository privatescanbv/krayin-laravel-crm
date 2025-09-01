<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add external_id (2025_01_20_120000)
            $table->string('external_id')->nullable();
            $table->index('external_id');
        });

        // Add audit trail (2025_07_25_000001)
        Schema::table('users', function (Blueprint $table) {
            AuditTrailMigrationHelper::addAuditTrailColumns($table);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove audit trail
            AuditTrailMigrationHelper::dropAuditTrailColumns($table);

            // Remove external_id
            $table->dropIndex(['external_id']);
            $table->dropColumn('external_id');
        });
    }
};