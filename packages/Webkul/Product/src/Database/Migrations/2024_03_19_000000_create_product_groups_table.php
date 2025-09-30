<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('product_groups')->onDelete('set null');
            $table->timestamps();
            AuditTrailMigrationHelper::addAuditTrailColumns($table);
        });

        // Add product_group_id to products table
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('product_group_id')->nullable()->constrained('product_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['product_group_id']);
            }
            $table->dropColumn('product_group_id');
        });

        Schema::dropIfExists('product_groups');
    }
};
