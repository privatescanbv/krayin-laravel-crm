<?php

namespace App\Helpers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditTrailMigrationHelper
{
    /**
     * Add audit trail columns to a table
     */
    public static function addAuditTrailColumns(Blueprint $table): void
    {
        $table->unsignedInteger('created_by')->nullable();
        $table->unsignedInteger('updated_by')->nullable();
        
        $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
    }

    /**
     * Add audit trail columns to a table only if they don't exist
     */
    public static function addAuditTrailColumnsIfNotExists(Blueprint $table, string $tableName): void
    {
        if (!Schema::hasColumn($tableName, 'created_by')) {
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        }
        
        if (!Schema::hasColumn($tableName, 'updated_by')) {
            $table->unsignedInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        }
    }

    /**
     * Drop audit trail columns from a table
     */
    public static function dropAuditTrailColumns(Blueprint $table): void
    {
        // Only drop foreign keys if not using SQLite (SQLite doesn't support dropping foreign keys)
        if (DB::getDriverName() !== 'sqlite') {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
        }
        
        $table->dropColumn(['created_by', 'updated_by']);
    }

    /**
     * Drop audit trail columns from a table only if they exist
     */
    public static function dropAuditTrailColumnsIfExists(Blueprint $table, string $tableName): void
    {
        // Only drop foreign keys if not using SQLite and columns exist
        if (DB::getDriverName() !== 'sqlite') {
            if (Schema::hasColumn($tableName, 'created_by')) {
                $table->dropForeign(['created_by']);
            }
            if (Schema::hasColumn($tableName, 'updated_by')) {
                $table->dropForeign(['updated_by']);
            }
        }
        
        // Drop columns only if they exist
        $columnsToDrop = [];
        if (Schema::hasColumn($tableName, 'created_by')) {
            $columnsToDrop[] = 'created_by';
        }
        if (Schema::hasColumn($tableName, 'updated_by')) {
            $columnsToDrop[] = 'updated_by';
        }
        
        if (!empty($columnsToDrop)) {
            $table->dropColumn($columnsToDrop);
        }
    }
}