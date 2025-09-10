<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Helpers\AuditTrailMigrationHelper;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_default_values', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('key');
            $table->string('value')->nullable();
            $table->timestamps();

            // Audit trail
            AuditTrailMigrationHelper::addAuditTrailColumns($table);

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_default_values', function (Blueprint $table) {
            // Drop audit trail columns and FKs first if present
            if (Schema::hasTable('user_default_values')) {
                AuditTrailMigrationHelper::dropAuditTrailColumnsIfExists($table, 'user_default_values');
            }
        });

        Schema::dropIfExists('user_default_values');
    }
};

