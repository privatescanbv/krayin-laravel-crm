<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_statuses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('status');
            $table->text('omschrijving')->nullable();
            $table->timestamps();

            $table->integer('activity_id')->unsigned();
            $table->foreign('activity_id')->references('id')->on('activities')->onDelete('cascade');

            AuditTrailMigrationHelper::addAuditTrailColumns($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_statuses');
    }
};
