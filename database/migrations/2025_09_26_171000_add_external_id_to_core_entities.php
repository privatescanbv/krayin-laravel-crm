<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['resources', 'resource_types', 'clinics', 'partner_products', 'product_types'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                if (! Schema::hasColumn($table->getTable(), 'external_id')) {
                    $table->string('external_id')->nullable()->after('name');
                    $table->index('external_id');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['resources', 'resource_types', 'clinics', 'partner_products', 'product_types'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                if (Schema::hasColumn($table->getTable(), 'external_id')) {
                    $table->dropIndex([$table->getTable().'_external_id_index']);
                    $table->dropColumn('external_id');
                }
            });
        }
    }
};
