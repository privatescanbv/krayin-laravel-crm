<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_orderitem', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('resource_id');
            $table->unsignedBigInteger('orderitem_id');
            $table->dateTime('from');
            $table->dateTime('to');

            $table->foreign('resource_id')->references('id')->on('resources')->onDelete('cascade');
            $table->foreign('orderitem_id')->references('id')->on('order_regels')->onDelete('cascade');

            AuditTrailMigrationHelper::addAuditTrailColumns($table);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('resource_orderitem', function (Blueprint $table) {
            AuditTrailMigrationHelper::dropAuditTrailColumnsIfExists($table, 'resource_orderitem');
        });

        Schema::dropIfExists('resource_orderitem');
    }
};

