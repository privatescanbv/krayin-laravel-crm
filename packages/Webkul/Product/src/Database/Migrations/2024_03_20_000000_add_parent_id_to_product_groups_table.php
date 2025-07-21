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
        Schema::table('product_groups', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->constrained('product_groups')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_groups', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') { $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
