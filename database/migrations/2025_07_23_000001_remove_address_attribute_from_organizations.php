<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // First get the attribute ID for the address attribute
        $addressAttribute = DB::table('attributes')->where([
            'code'        => 'address',
            'entity_type' => 'organizations',
        ])->first();

        if ($addressAttribute) {
            // Remove any attribute values for the address attribute
            DB::table('attribute_values')->where([
                'attribute_id' => $addressAttribute->id,
                'entity_type'  => 'organizations',
            ])->delete();

            // Remove the address attribute from organizations
            DB::table('attributes')->where('id', $addressAttribute->id)->delete();
        }
    }

    public function down(): void
    {
        // Re-add the address attribute
        DB::table('attributes')->insert([
            'code'            => 'address',
            'name'            => 'Adres',
            'type'            => 'address',
            'entity_type'     => 'organizations',
            'lookup_type'     => null,
            'validation'      => null,
            'sort_order'      => 2,
            'is_required'     => 0,
            'is_unique'       => 0,
            'quick_add'       => 1,
            'is_user_defined' => 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }
};
