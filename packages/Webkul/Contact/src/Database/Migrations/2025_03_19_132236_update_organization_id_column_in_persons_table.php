<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            // SQLite doesn't support dropping foreign keys, so skip this for SQLite
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['organization_id']);
                $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            // SQLite doesn't support dropping foreign keys, so skip this for SQLite
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['organization_id']);
                $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            }
        });
    }
};
