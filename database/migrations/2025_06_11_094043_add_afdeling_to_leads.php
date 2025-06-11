<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Check if attribute exists
        $attributeId = DB::table('attributes')
            ->where('code', 'department')
            ->where('entity_type', 'leads')
            ->value('id');

        if (!$attributeId) {
            // Create new attribute if it doesn't exist
            $attributeId = DB::table('attributes')->insertGetId([
                'code' => 'department',
                'name' => 'Afdeling',
                'type' => 'select',
                'entity_type' => 'leads',
                'lookup_type' => null,
                'validation' => null,
                'sort_order' => 5,
                'is_required' => 0,
                'is_unique' => 0,
                'quick_add' => 1,
                'is_user_defined' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            // Update existing attribute
            DB::table('attributes')
                ->where('id', $attributeId)
                ->update([
                    'name' => 'Afdeling',
                    'type' => 'select',
                    'lookup_type' => 'department',
                    'sort_order' => 5,
                    'is_required' => 0,
                    'is_unique' => 0,
                    'quick_add' => 1,
                    'is_user_defined' => 1,
                    'updated_at' => now(),
                ]);
        }

        // Delete existing options if any
        DB::table('attribute_options')
            ->where('attribute_id', $attributeId)
            ->delete();

        // Create the options
        DB::table('attribute_options')->insert([
            [
                'attribute_id' => $attributeId,
                'name' => 'Privatescan',
                'sort_order' => 1
            ],
            [
                'attribute_id' => $attributeId,
                'name' => 'Hernia',
                'sort_order' => 2
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // First get the attribute ID
        $attributeId = DB::table('attributes')
            ->where('code', 'department')
            ->where('entity_type', 'leads')
            ->value('id');

        if ($attributeId) {
            // Delete the options
            DB::table('attribute_options')
                ->where('attribute_id', $attributeId)
                ->delete();

            // Delete the attribute
            DB::table('attributes')
                ->where('id', $attributeId)
                ->delete();
        }
    }
};
