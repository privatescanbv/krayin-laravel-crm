<?php

use App\Enums\CallStatusEnum;
use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('activity_id');
            $table->string('status');
            $table->text('omschrijving')->nullable();
            $table->timestamps();

            $table->foreign('activity_id')
                ->references('id')
                ->on('activities')
                ->onDelete('cascade');

            AuditTrailMigrationHelper::addAuditTrailColumns($table);
        });
    }

    public function down(): void
    {
        Schema::table('call_statuses', function (Blueprint $table) {
            AuditTrailMigrationHelper::dropAuditTrailColumnsIfExists($table, 'call_statuses');
        });

        Schema::dropIfExists('call_statuses');
    }
};

