<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('attributes')->insert([
            'code'            => 'costs',
            'name'            => 'Kosten',
            'type'            => 'price',
            'entity_type'     => 'products',
            'lookup_type'     => null,
            'validation'      => '',
            'is_required'     => 0,
            'is_unique'       => 0,
            'quick_add'       => 1,
            'is_user_defined' => 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('attributes')
            ->where('code', 'costs')
            ->where('entity_type', 'products')
            ->delete();
    }
};
