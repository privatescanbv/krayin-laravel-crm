<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // organizations.id is increments() => unsigned INT; must match for MySQL 8 FK (3780)
            $table->unsignedInteger('organization_id')->nullable()->after('is_business');
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
