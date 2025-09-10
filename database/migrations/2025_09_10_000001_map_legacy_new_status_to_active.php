<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('activities')->where('status', 'new')->update(['status' => 'active']);
    }

    public function down(): void
    {
        // No-op: we don't want to revert back to legacy value
    }
};

