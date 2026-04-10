<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $map = [
            'App\\Models\\OrderItem'      => 'order_items',
            'App\\Models\\PartnerProduct' => 'partner_products',
        ];

        foreach ($map as $fqcn => $alias) {
            DB::table('purchase_prices')
                ->where('priceable_type', $fqcn)
                ->update(['priceable_type' => $alias]);
        }
    }

    public function down(): void
    {
        $map = [
            'order_items'      => 'App\\Models\\OrderItem',
            'partner_products' => 'App\\Models\\PartnerProduct',
        ];

        foreach ($map as $alias => $fqcn) {
            DB::table('purchase_prices')
                ->where('priceable_type', $alias)
                ->update(['priceable_type' => $fqcn]);
        }
    }
};
