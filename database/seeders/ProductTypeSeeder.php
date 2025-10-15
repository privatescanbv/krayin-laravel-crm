<?php

namespace Database\Seeders;

use App\Enums\ProductType as ProductTypeEnum;
use App\Models\ProductType;

class ProductTypeSeeder extends BaseSeeder
{
    public function run(): void
    {
        $this->truncateTables(['product_types']);

        $defaults = [
            ['external_id' => '01', 'name' => ProductTypeEnum::TOTAL_BODYSCAN->label()],
            ['external_id' => '02', 'name' => ProductTypeEnum::MRI_SCAN->label()],
            ['external_id' => '03', 'name' => ProductTypeEnum::CT_SCAN->label()],
            ['external_id' => '04', 'name' => ProductTypeEnum::CARDIOLOGIE->label()],
            ['external_id' => '05', 'name' => ProductTypeEnum::ENDOSCOPIE->label()],
            ['external_id' => '06', 'name' => ProductTypeEnum::PETSCAN->label()],
            ['external_id' => '07', 'name' => ProductTypeEnum::LABORATORIUM->label()],
            ['external_id' => '09', 'name' => ProductTypeEnum::VERTALING->label()],
            ['external_id' => '10', 'name' => ProductTypeEnum::DIENSTEN->label()],
            ['external_id' => '11', 'name' => ProductTypeEnum::OVERIG->label()],
        ];

        foreach ($defaults as $data) {
            ProductType::create($data);
        }
    }
}
