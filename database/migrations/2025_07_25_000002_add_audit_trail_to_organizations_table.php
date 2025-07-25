<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            AuditTrailMigrationHelper::addAuditTrailColumnsIfNotExists($table, 'organizations');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            AuditTrailMigrationHelper::dropAuditTrailColumnsIfExists($table, 'organizations');
        });
    }
};
