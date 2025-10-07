<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_runs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status')->default('running'); // running, completed, failed
            $table->string('import_type')->nullable(); // leads, persons, users, email-attachments
            $table->integer('records_processed')->default(0);
            $table->integer('records_imported')->default(0);
            $table->integer('records_skipped')->default(0);
            $table->integer('records_errored')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_runs');
    }
};
