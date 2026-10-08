<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot of who the person was to us when the lead came in (CustomerHistoryService).
 * Stored rather than derived so a later purchase does not rewrite older leads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('customer_type', 30)->nullable()->index();
            $table->unsignedSmallInteger('prior_lead_count')->nullable();
            $table->unsignedSmallInteger('prior_purchase_count')->nullable();
            $table->date('last_purchase_at')->nullable();
            $table->dateTime('customer_type_determined_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['customer_type']);
            $table->dropColumn([
                'customer_type',
                'prior_lead_count',
                'prior_purchase_count',
                'last_purchase_at',
                'customer_type_determined_at',
            ]);
        });
    }
};
