<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->string('street')->nullable();
            $table->string('house_number');
            $table->string('postal_code');
            $table->string('house_number_suffix')->nullable(); // Addition (apartment, unit, floor)
            $table->string('state')->nullable(); // Optional state or province
            $table->string('city')->nullable();
            $table->string('country')->nullable();

            // Foreign key for Lead (nullable since not all addresses belong to leads)
            $table->unsignedInteger('lead_id')->nullable();
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');

            // Foreign key for Person (nullable since not all addresses belong to persons)
            $table->unsignedInteger('person_id')->nullable();
            $table->foreign('person_id')->references('id')->on('persons')->onDelete('cascade');

            $table->timestamps();

            // Add audit trail columns
            AuditTrailMigrationHelper::addAuditTrailColumns($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
