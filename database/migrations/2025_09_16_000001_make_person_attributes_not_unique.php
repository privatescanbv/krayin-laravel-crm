<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure the attributes table and is_unique column exist, then disable uniqueness for person attributes
        if (Schema::hasTable('attributes') && Schema::hasColumn('attributes', 'is_unique') && Schema::hasColumn('attributes', 'entity_type')) {
            DB::table('attributes')
                ->where('entity_type', 'persons')
                ->update(['is_unique' => 0]);
        }
    }

    public function down(): void
    {
        // No-op: previous unique flags are unknown; intentionally left blank.
    }
};
