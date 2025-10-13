<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('activities', 'sales_lead_id')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->unsignedBigInteger('sales_lead_id')->nullable()->after('lead_id');

                // Foreign key is optional here to keep sqlite test compatibility
                // If needed in MySQL, you can add it via a separate guarded migration.
                // try {
                //     $table->foreign('sales_lead_id')->references('id')->on('salesleads')->onDelete('cascade');
                // } catch (\Throwable $e) {}
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('activities', 'sales_lead_id')) {
            Schema::table('activities', function (Blueprint $table) {
                // if (Schema::hasColumn('activities', 'sales_lead_id')) {
                //     $table->dropForeign(['sales_lead_id']);
                // }
                $table->dropColumn('sales_lead_id');
            });
        }
    }
};
