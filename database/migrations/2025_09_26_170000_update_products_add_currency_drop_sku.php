<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'currency')) {
                $table->string('currency', 3)->default('EUR')->after('name');
            }
            if (Schema::hasColumn('products', 'sku')) {
                $table->dropUnique(['sku']);
                $table->dropColumn('sku');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'sku')) {
                $table->string('sku')->nullable();
            }
            if (Schema::hasColumn('products', 'currency')) {
                $table->dropColumn('currency');
            }
        });
    }
};

