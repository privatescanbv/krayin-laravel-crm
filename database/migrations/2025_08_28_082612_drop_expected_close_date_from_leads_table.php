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
        // This migration is no longer needed since the expected_close_date field
        // has been completely removed from the codebase and the original migration
        // that created the column has also been removed.
        // 
        // For fresh installations, this column will never exist.
        // For existing installations, manual cleanup may be required.
        
        // Do nothing - the field has been removed from all code
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Do nothing - we don't want to recreate the field
    }
};