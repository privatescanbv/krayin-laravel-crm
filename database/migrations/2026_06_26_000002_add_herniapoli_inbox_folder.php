<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Only insert if it does not already exist
        $exists = DB::table('folders')
            ->where('name', 'inbox_herniapoli')
            ->whereNull('parent_id')
            ->exists();

        if (! $exists) {
            // Determine next order value
            $maxOrder = DB::table('folders')->max('order') ?? 0;

            DB::table('folders')->insert([
                'name'         => 'inbox_herniapoli',
                'parent_id'    => null,
                'order'        => $maxOrder + 1,
                'is_deletable' => false,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('folders')
            ->where('name', 'inbox_herniapoli')
            ->whereNull('parent_id')
            ->delete();
    }
};
