<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('attributes')->insert([
            'code'            => 'resource_type_id',
            'name'            => 'Resourcetype',
            'type'            => 'lookup',
            'entity_type'     => 'products',
            'lookup_type'     => 'resource_types',
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
            ->where('code', 'resource_type_id')
            ->where('entity_type', 'products')
            ->delete();
    }
};
