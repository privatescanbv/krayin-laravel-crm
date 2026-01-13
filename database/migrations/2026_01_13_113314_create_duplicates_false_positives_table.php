<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duplicates_false_positives', function (Blueprint $table) {
            $table->id();

            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id_1');
            $table->unsignedBigInteger('entity_id_2');

            $table->text('reason')->nullable();

            AuditTrailMigrationHelper::addAuditTrailColumns($table);

            $table->unique(
                ['entity_type', 'entity_id_1', 'entity_id_2'],
                'dfp_entity_pair_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duplicates_false_positives');
    }
};
