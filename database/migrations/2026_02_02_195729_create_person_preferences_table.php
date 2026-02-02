<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('person_id');
            $table->string('key', 100);
            $table->json('value');
            $table->string('value_type', 20);
            $table->boolean('is_system_managed')->default(false);
            AuditTrailMigrationHelper::addAuditTrailColumns($table);
            $table->timestamps();

            $table->foreign('person_id')
                ->references('id')
                ->on('persons')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_preferences');
    }
};
