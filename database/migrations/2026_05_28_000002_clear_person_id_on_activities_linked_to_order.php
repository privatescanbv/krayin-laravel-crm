<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('activities')
            ->whereNotNull('person_id')
            ->whereNotNull('order_id')
            ->update(['person_id' => null]);
    }

    public function down(): void
    {
        // Data migration: person_id values cannot be restored.
    }
};
