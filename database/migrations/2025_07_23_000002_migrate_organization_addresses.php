<?php

use App\Models\Address;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // First migrate data from the organizations.address column
        $organizationsWithAddress = DB::table('organizations')
            ->whereNotNull('address')
            ->get();

        foreach ($organizationsWithAddress as $organization) {
            $addressData = json_decode($organization->address, true);

            if ($addressData && is_array($addressData)) {
                Address::create([
                    'organization_id'     => $organization->id,
                    'street'              => $addressData['street'] ?? null,
                    'house_number'        => $addressData['house_number'] ?? null,
                    'postal_code'         => $addressData['postal_code'] ?? null,
                    'house_number_suffix' => $addressData['house_number_suffix'] ?? null,
                    'state'               => $addressData['state'] ?? null,
                    'city'                => $addressData['city'] ?? null,
                    'country'             => $addressData['country'] ?? 'Nederland',
                ]);
            }
        }

        // Then migrate data from attribute_values (if any exists)
        $addressAttribute = DB::table('attributes')->where([
            'code'        => 'address',
            'entity_type' => 'organizations',
        ])->first();

        if ($addressAttribute) {
            $addressValues = DB::table('attribute_values')
                ->where('attribute_id', $addressAttribute->id)
                ->where('entity_type', 'organizations')
                ->get();

            foreach ($addressValues as $addressValue) {
                // Skip if we already created an address for this organization
                $existingAddress = Address::where('organization_id', $addressValue->entity_id)->first();
                if ($existingAddress) {
                    continue;
                }

                $addressData = json_decode($addressValue->json_value, true);

                if ($addressData && is_array($addressData)) {
                    Address::create([
                        'organization_id'     => $addressValue->entity_id,
                        'street'              => $addressData['street'] ?? null,
                        'house_number'        => $addressData['house_number'] ?? null,
                        'postal_code'         => $addressData['postal_code'] ?? null,
                        'house_number_suffix' => $addressData['house_number_suffix'] ?? null,
                        'state'               => $addressData['state'] ?? null,
                        'city'                => $addressData['city'] ?? null,
                        'country'             => $addressData['country'] ?? 'Nederland',
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Remove all organization addresses
        Address::where('organization_id', '!=', null)->delete();
    }
};
