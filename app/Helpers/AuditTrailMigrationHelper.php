<?php

namespace App\Helpers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

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
}