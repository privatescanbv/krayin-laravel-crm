<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const EXTERNAL_ID = '1132';

    public function up(): void
    {
        if (DB::table('products')->where('external_id', self::EXTERNAL_ID)->exists()) {
            // Existing Korting from ProductSeeder still has its old 1,00 placeholder price;
            // a price someone already changed by hand is left alone.
            DB::table('products')
                ->where('external_id', self::EXTERNAL_ID)
                ->where('price', 1)
                ->update(['price' => -50.00, 'updated_at' => now()]);

            return;
        }

        $resourceTypeId = DB::table('resource_types')->where('name', 'Other')->value('id');
        $productTypeId = DB::table('product_types')->where('name', 'Diensten')->value('id');
        $productGroupId = DB::table('product_groups')->where('name', 'Administratief')->value('id');

        DB::table('products')->insert([
            'name'             => 'Korting',
            'external_id'      => self::EXTERNAL_ID,
            'description'      => 'Korting',
            'currency'         => 'EUR',
            'price'            => -50.00,
            'resource_type_id' => $resourceTypeId,
            'product_type_id'  => $productTypeId,
            'product_group_id' => $productGroupId,
            'active'           => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('products')->where('external_id', self::EXTERNAL_ID)->delete();
    }
};
