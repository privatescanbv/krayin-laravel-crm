<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_pipeline_stages', function (Blueprint $table) {
            $table->boolean('is_won')->default(false)->after('probability');
            $table->boolean('is_lost')->default(false)->after('is_won');
        });
    }

    public function down(): void
    {
        Schema::table('lead_pipeline_stages', function (Blueprint $table) {
            $table->dropColumn(['is_won', 'is_lost']);
        });
    }
};
