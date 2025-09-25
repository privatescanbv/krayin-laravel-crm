<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Helpers\AuditTrailMigrationHelper;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->json('emails')->nullable();
            $table->json('phones')->nullable();
            $table->timestamps();
            AuditTrailMigrationHelper::addAuditTrailColumns($table);

            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinics');
    }
};

