<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->unsignedBigInteger('workflow_lead_id')->nullable()->after('lead_id');
            $table->foreign('workflow_lead_id')->references('id')->on('salesleads')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropForeign(['workflow_lead_id']);
            $table->dropColumn('workflow_lead_id');
        });
    }
};