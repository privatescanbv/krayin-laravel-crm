<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite doesn't support multiple renameColumn in single modification
            // Rename fields in persons table - one by one
            Schema::table('persons', function (Blueprint $table) {
                $table->renameColumn('maiden_name', 'married_name');
            });
            Schema::table('persons', function (Blueprint $table) {
                $table->renameColumn('maiden_name_prefix', 'married_name_prefix');
            });

            // Rename fields in leads table - one by one
            Schema::table('leads', function (Blueprint $table) {
                $table->renameColumn('maiden_name', 'married_name');
            });
            Schema::table('leads', function (Blueprint $table) {
                $table->renameColumn('maiden_name_prefix', 'married_name_prefix');
            });
        } else {
            // MySQL/PostgreSQL can handle multiple renames in one call
            // Rename fields in persons table
            Schema::table('persons', function (Blueprint $table) {
                $table->renameColumn('maiden_name', 'married_name');
                $table->renameColumn('maiden_name_prefix', 'married_name_prefix');
            });

            // Rename fields in leads table
            Schema::table('leads', function (Blueprint $table) {
                $table->renameColumn('maiden_name', 'married_name');
                $table->renameColumn('maiden_name_prefix', 'married_name_prefix');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite doesn't support multiple renameColumn in single modification
            // Revert changes in persons table - one by one
            Schema::table('persons', function (Blueprint $table) {
                $table->renameColumn('married_name', 'maiden_name');
            });
            Schema::table('persons', function (Blueprint $table) {
                $table->renameColumn('married_name_prefix', 'maiden_name_prefix');
            });

            // Revert changes in leads table - one by one
            Schema::table('leads', function (Blueprint $table) {
                $table->renameColumn('married_name', 'maiden_name');
            });
            Schema::table('leads', function (Blueprint $table) {
                $table->renameColumn('married_name_prefix', 'maiden_name_prefix');
            });
        } else {
            // MySQL/PostgreSQL can handle multiple renames in one call
            // Revert changes in persons table
            Schema::table('persons', function (Blueprint $table) {
                $table->renameColumn('married_name', 'maiden_name');
                $table->renameColumn('married_name_prefix', 'maiden_name_prefix');
            });

            // Revert changes in leads table
            Schema::table('leads', function (Blueprint $table) {
                $table->renameColumn('married_name', 'maiden_name');
                $table->renameColumn('married_name_prefix', 'maiden_name_prefix');
            });
        }
    }
};
