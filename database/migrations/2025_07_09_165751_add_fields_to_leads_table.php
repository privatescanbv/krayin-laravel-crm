<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('lead_channel_id')->nullable()->after('title');
            $table->foreign('lead_channel_id')->references('id')->on('lead_channels')->nullOnDelete();
            $table->unsignedBigInteger('department_id')->nullable()->after('lead_channel_id');
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('lead_channel_id');
            $table->dropColumn('department_id');
        });
    }
};
