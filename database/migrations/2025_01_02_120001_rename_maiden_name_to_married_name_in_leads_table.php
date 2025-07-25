<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Rename maiden_name to married_name
            $table->renameColumn('maiden_name', 'married_name');
            $table->renameColumn('maiden_name_prefix', 'married_name_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Rename back to maiden_name
            $table->renameColumn('married_name', 'maiden_name');
            $table->renameColumn('married_name_prefix', 'maiden_name_prefix');
        });
    }
};