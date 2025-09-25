<?php

namespace Database\Seeders;

use App\Models\ResourceType;

class ResourceTypeSeeder extends BaseSeeder
{
    public function run(): void
    {
        $this->truncateTables(['resource_types']);

        $defaults = [
            ['name' => 'MRI Scanner', 'description' => 'Magnetic resonance imaging device'],
            ['name' => 'CT Scanner', 'description' => 'Computed tomography device'],
            ['name' => 'X-Ray', 'description' => 'Radiography imaging equipment'],
            ['name' => 'Ultrasound', 'description' => 'Diagnostic ultrasound system'],
            ['name' => 'Mammography', 'description' => 'Breast imaging system'],
        ];

        foreach ($defaults as $data) {
            ResourceType::create($data);
        }
    }
}

