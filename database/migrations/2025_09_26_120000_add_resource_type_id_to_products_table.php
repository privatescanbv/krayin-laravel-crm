<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'resource_type_id')) {
                $table->unsignedBigInteger('resource_type_id')->nullable()->after('price');
                $table->unsignedBigInteger('product_type_id')->nullable()->after('resource_type_id');
                $table->foreign('resource_type_id')->references('id')->on('resource_types')->onDelete('set null');
                $table->foreign('product_type_id')->references('id')->on('product_types')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'resource_type_id')) {
                $table->dropForeign(['resource_type_id']);
                $table->dropColumn('resource_type_id');
            }
        });
    }
};
