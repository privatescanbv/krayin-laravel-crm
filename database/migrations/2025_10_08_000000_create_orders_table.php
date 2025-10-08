<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('sales_order_id')->nullable();
            $table->decimal('total_price', 12, 2)->default(0);
            AuditTrailMigrationHelper::addAuditTrailColumns($table);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            AuditTrailMigrationHelper::dropAuditTrailColumnsIfExists($table, 'orders');
        });

        Schema::dropIfExists('orders');
    }
};

