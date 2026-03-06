<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('partner_products')->orderBy('id')->chunk(100, function ($rows) use ($now) {
            foreach ($rows as $pp) {
                $mainTotal = ($pp->purchase_price_misc ?? 0)
                    + ($pp->purchase_price_doctor ?? 0)
                    + ($pp->purchase_price_cardiology ?? 0)
                    + ($pp->purchase_price_clinic ?? 0)
                    + ($pp->purchase_price_radiology ?? 0);

                DB::table('purchase_prices')->insert([
                    'priceable_type'            => 'App\\Models\\PartnerProduct',
                    'priceable_id'              => $pp->id,
                    'type'                      => 'main',
                    'purchase_price_misc'       => $pp->purchase_price_misc,
                    'purchase_price_doctor'     => $pp->purchase_price_doctor,
                    'purchase_price_cardiology' => $pp->purchase_price_cardiology,
                    'purchase_price_clinic'     => $pp->purchase_price_clinic,
                    'purchase_price_radiology'  => $pp->purchase_price_radiology,
                    'purchase_price'            => $pp->purchase_price ?? $mainTotal,
                    'created_at'                => $now,
                    'updated_at'                => $now,
                ]);

                $relTotal = ($pp->rel_purchase_price_misc ?? 0)
                    + ($pp->rel_purchase_price_doctor ?? 0)
                    + ($pp->rel_purchase_price_cardiology ?? 0)
                    + ($pp->rel_purchase_price_clinic ?? 0)
                    + ($pp->rel_purchase_price_radiology ?? 0);

                DB::table('purchase_prices')->insert([
                    'priceable_type'            => 'App\\Models\\PartnerProduct',
                    'priceable_id'              => $pp->id,
                    'type'                      => 'related',
                    'purchase_price_misc'       => $pp->rel_purchase_price_misc,
                    'purchase_price_doctor'     => $pp->rel_purchase_price_doctor,
                    'purchase_price_cardiology' => $pp->rel_purchase_price_cardiology,
                    'purchase_price_clinic'     => $pp->rel_purchase_price_clinic,
                    'purchase_price_radiology'  => $pp->rel_purchase_price_radiology,
                    'purchase_price'            => $pp->rel_purchase_price ?? $relTotal,
                    'created_at'                => $now,
                    'updated_at'                => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('purchase_prices')
            ->where('priceable_type', 'App\\Models\\PartnerProduct')
            ->delete();
    }
};
