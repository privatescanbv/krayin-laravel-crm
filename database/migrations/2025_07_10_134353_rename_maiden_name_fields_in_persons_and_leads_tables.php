<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
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
};
