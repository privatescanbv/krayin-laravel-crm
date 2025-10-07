<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('import_run_id');
            $table->string('level'); // error, warning, info
            $table->text('message');
            $table->json('context')->nullable();
            $table->string('record_id')->nullable();
            $table->timestamps();
            AuditTrailMigrationHelper::addAuditTrailColumns($table);

            $table->foreign('import_run_id')->references('id')->on('import_runs')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};