<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('resource_id');
            $table->unsignedBigInteger('order_item_id');
            $table->dateTime('from');
            $table->dateTime('to');

            $table->foreign('resource_id')->references('id')->on('resources')->onDelete('cascade');
            $table->foreign('order_item_id')->references('id')->on('order_items')->onDelete('cascade');

            AuditTrailMigrationHelper::addAuditTrailColumns($table);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('resource_order_items', function (Blueprint $table) {
            AuditTrailMigrationHelper::dropAuditTrailColumnsIfExists($table, 'resource_order_items');
        });

        Schema::dropIfExists('resource_order_items');
    }
};
