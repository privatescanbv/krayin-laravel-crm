<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // First, ensure all activities have a group_id by setting a default for null values
        // We'll use the first available group as default
        $defaultGroupId = DB::table('groups')->first()?->id;
        
        if ($defaultGroupId) {
            DB::table('activities')
                ->whereNull('group_id')
                ->update(['group_id' => $defaultGroupId]);
        }

        // Now make the column required (not nullable)
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedInteger('group_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedInteger('group_id')->nullable()->change();
        });
    }
};