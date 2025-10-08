<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_regels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('quantity');
            $table->decimal('total_price', 12, 2)->default(0);
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            AuditTrailMigrationHelper::addAuditTrailColumns($table);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('order_regels', function (Blueprint $table) {
            AuditTrailMigrationHelper::dropAuditTrailColumnsIfExists($table, 'order_regels');
        });

        Schema::dropIfExists('order_regels');
    }
};

