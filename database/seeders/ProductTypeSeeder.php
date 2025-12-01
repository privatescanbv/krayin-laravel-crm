<?php

namespace Database\Seeders;

use App\Enums\ProductType as ProductTypeEnum;
use App\Models\ProductType;

class ProductTypeSeeder extends BaseSeeder
{
    public function run(): void
    {
        $this->truncateTables(['product_types']);

        // Map enum cases to external IDs; iterate dynamically over the enum
        $externalIdMap = [
            ProductTypeEnum::TOTAL_BODYSCAN->value => '01',
            ProductTypeEnum::MRI_SCAN->value       => '02',
            ProductTypeEnum::CT_SCAN->value        => '03',
            ProductTypeEnum::CARDIOLOGIE->value    => '04',
            ProductTypeEnum::ENDOSCOPIE->value     => '05',
            ProductTypeEnum::PETSCAN->value        => '06',
            ProductTypeEnum::LABORATORIUM->value   => '07',
            ProductTypeEnum::VERTALING->value      => '09',
            ProductTypeEnum::DIENSTEN->value       => '10',
            ProductTypeEnum::OVERIG->value         => '11',
            ProductTypeEnum::OPERATIONS->value     => '12',
        ];

        foreach (ProductTypeEnum::cases() as $case) {
            // Only seed cases that have an external ID defined
            if (! array_key_exists($case->value, $externalIdMap)) {
                continue;
            }

            ProductType::create([
                'external_id' => $externalIdMap[$case->value],
                'name'        => $case->label(),
            ]);
        }
    }
}
