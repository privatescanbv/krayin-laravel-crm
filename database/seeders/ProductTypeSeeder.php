<?php

namespace Database\Seeders;

use App\Models\ProductType;
use Illuminate\Database\Seeder;

class ProductTypeSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Good', 'description' => null],
            ['name' => 'Service', 'description' => null],
            ['name' => '01 Total Bodyscan', 'description' => null],
            ['name' => '02 MRI scan', 'description' => null],
            ['name' => '03 CT scan', 'description' => null],
            ['name' => '04 Cardiologie', 'description' => null],
            ['name' => '05 Endoscopie', 'description' => null],
            ['name' => '06 PET scan', 'description' => null],
            ['name' => '07 Laboratorium', 'description' => null],
            ['name' => '08 CCSVI', 'description' => null],
            ['name' => '09 Vertaling', 'description' => null],
            ['name' => '10 Diensten', 'description' => null],
            ['name' => '11 Overige', 'description' => null],
        ];

        foreach ($items as $item) {
            ProductType::firstOrCreate(['name' => $item['name']], $item);
        }
    }
}

