<?php

namespace Database\Seeders;

use App\Enums\ResourceType as ResourceTypeEnum;
use App\Models\ResourceType;

class ResourceTypeSeeder extends BaseSeeder
{
    public function run(): void
    {
        $this->truncateTables(['resource_types']);

        $defaults = [
            ['name' => ResourceTypeEnum::MRI_SCANNER->label()],
            ['name' => ResourceTypeEnum::CT_SCANNER->label()],
            ['name' => ResourceTypeEnum::PET_CT_SCANNER->label()],
            ['name' => ResourceTypeEnum::ARTSEN->label()],
            ['name' => ResourceTypeEnum::OTHER->label()],
            ['name' => ResourceTypeEnum::CARDIOLOGIE->label()],
        ];

        foreach ($defaults as $data) {
            ResourceType::create($data);
        }
    }
}
