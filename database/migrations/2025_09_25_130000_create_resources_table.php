<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type');
            $table->string('name');
            $table->unsignedBigInteger('clinic_id')->nullable();
            $table->timestamps();
            AuditTrailMigrationHelper::addAuditTrailColumns($table);

            $table->index('name');
            $table->foreign('clinic_id')->references('id')->on('clinics')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
