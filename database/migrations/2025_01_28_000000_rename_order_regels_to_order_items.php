<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Rename the table
        Schema::rename('order_regels', 'order_items');
        
        // Update the foreign key in resource_order_items table
        Schema::table('resource_order_items', function (Blueprint $table) {
            $table->dropForeign(['orderitem_id']);
            $table->renameColumn('orderitem_id', 'order_item_id');
        });
        
        // Re-add the foreign key constraint
        Schema::table('resource_order_items', function (Blueprint $table) {
            $table->foreign('order_item_id')->references('id')->on('order_items')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the foreign key constraint
        Schema::table('resource_order_items', function (Blueprint $table) {
            $table->dropForeign(['order_item_id']);
            $table->renameColumn('order_item_id', 'orderitem_id');
        });
        
        // Re-add the foreign key constraint
        Schema::table('resource_order_items', function (Blueprint $table) {
            $table->foreign('orderitem_id')->references('id')->on('order_regels')->onDelete('cascade');
        });
        
        // Rename the table back
        Schema::rename('order_items', 'order_regels');
    }
};