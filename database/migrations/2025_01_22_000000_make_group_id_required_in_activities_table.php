<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            // First, update any NULL group_id values to a default group
            // We'll handle this in the application logic before making it required
            $table->unsignedInteger('group_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedInteger('group_id')->nullable()->change();
        });
    }
};