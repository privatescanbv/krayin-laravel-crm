<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('activities', 'activities_order_id_is_done_idx')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table) {
            $table->index(['order_id', 'is_done'], 'activities_order_id_is_done_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('activities', 'activities_order_id_is_done_idx')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex('activities_order_id_is_done_idx');
        });
    }
};
