<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_runs', function (Blueprint $table) {
            AuditTrailMigrationHelper::addAuditTrailColumnsIfNotExists($table, 'import_runs');
        });
    }

    public function down(): void
    {
        Schema::table('import_runs', function (Blueprint $table) {
            AuditTrailMigrationHelper::dropAuditTrailColumnsIfExists($table, 'import_runs');
        });
    }
};
