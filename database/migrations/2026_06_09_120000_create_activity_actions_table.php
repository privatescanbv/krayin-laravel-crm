<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_actions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('activity_id');
            $table->string('type');         // ActivityActionType enum value
            $table->text('body')->nullable();
            $table->string('call_status')->nullable();
            $table->smallInteger('reschedule_days')->nullable();
            $table->timestamps();

            $table->foreign('activity_id')->references('id')->on('activities')->onDelete('cascade');

            AuditTrailMigrationHelper::addAuditTrailColumns($table);
        });

        // Migrate existing activity_comments → activity_actions (type = notitie)
        if (Schema::hasTable('activity_comments')) {
            DB::statement("
                INSERT INTO activity_actions
                    (activity_id, type, body, created_by, updated_by, created_at, updated_at)
                SELECT
                    activity_id, 'notitie', comment, created_by, updated_by, created_at, updated_at
                FROM activity_comments
            ");
        }

        // Migrate existing call_statuses → activity_actions (type = belstatus)
        if (Schema::hasTable('call_statuses')) {
            DB::statement("
                INSERT INTO activity_actions
                    (activity_id, type, body, call_status, created_by, updated_by, created_at, updated_at)
                SELECT
                    activity_id, 'belstatus', omschrijving, status, created_by, updated_by, created_at, updated_at
                FROM call_statuses
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_actions');
    }
};
