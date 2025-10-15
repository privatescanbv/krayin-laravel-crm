<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename the main table
        Schema::rename('order_regels', 'order_items');
        
        // Update foreign key reference in resource_orderitem table
        Schema::table('resource_orderitem', function (Blueprint $table) {
            $table->dropForeign(['orderitem_id']);
        });
        
        Schema::table('resource_orderitem', function (Blueprint $table) {
            $table->foreign('orderitem_id')->references('id')->on('order_items')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // Revert foreign key reference in resource_orderitem table
        Schema::table('resource_orderitem', function (Blueprint $table) {
            $table->dropForeign(['orderitem_id']);
        });
        
        Schema::table('resource_orderitem', function (Blueprint $table) {
            $table->foreign('orderitem_id')->references('id')->on('order_regels')->onDelete('cascade');
        });
        
        // Rename the table back
        Schema::rename('order_items', 'order_regels');
    }
};