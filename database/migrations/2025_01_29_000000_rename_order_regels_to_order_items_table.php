<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename the main table when it exists (sqlite tests may not have it)
        if (Schema::hasTable('order_regels')) {
            Schema::rename('order_regels', 'order_items');
        }

        // Update foreign key reference in resource_orderitem table
        if (
            Schema::hasTable('resource_orderitem') &&
            Schema::hasColumn('resource_orderitem', 'orderitem_id')
        ) {
            // Dropping a foreign key can fail on sqlite if it never existed; ignore in that case
            try {
                Schema::table('resource_orderitem', function (Blueprint $table) {
                    $table->dropForeign(['orderitem_id']);
                });
            } catch (Throwable $e) {
                // no-op
            }

            if (Schema::hasTable('order_items')) {
                Schema::table('resource_orderitem', function (Blueprint $table) {
                    $table->foreign('orderitem_id')->references('id')->on('order_items')->onDelete('cascade');
                });
            }
        }
    }

    public function down(): void
    {
        // Revert foreign key reference in resource_orderitem table
        if (
            Schema::hasTable('resource_orderitem') &&
            Schema::hasColumn('resource_orderitem', 'orderitem_id')
        ) {
            try {
                Schema::table('resource_orderitem', function (Blueprint $table) {
                    $table->dropForeign(['orderitem_id']);
                });
            } catch (Throwable $e) {
                // no-op
            }

            if (Schema::hasTable('order_regels')) {
                Schema::table('resource_orderitem', function (Blueprint $table) {
                    $table->foreign('orderitem_id')->references('id')->on('order_regels')->onDelete('cascade');
                });
            }
        }

        // Rename the table back
        if (Schema::hasTable('order_items')) {
            Schema::rename('order_items', 'order_regels');
        }
    }
};
