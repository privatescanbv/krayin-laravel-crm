<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_groups', function (Blueprint $table) {
            AuditTrailMigrationHelper::addAuditTrailColumns($table);
        });
    }

    public function down(): void
    {
        Schema::table('product_groups', function (Blueprint $table) {
            AuditTrailMigrationHelper::dropAuditTrailColumns($table);
        });
    }
};