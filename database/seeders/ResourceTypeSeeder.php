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
            ['external_id' => ResourceTypeEnum::MRI_SCANNER->value, 'name' => ResourceTypeEnum::MRI_SCANNER->label()],
            ['external_id' => ResourceTypeEnum::CT_SCANNER->value, 'name' => ResourceTypeEnum::CT_SCANNER->label()],
            ['external_id' => ResourceTypeEnum::PET_CT_SCANNER->value, 'name' => ResourceTypeEnum::PET_CT_SCANNER->label()],
            ['external_id' => ResourceTypeEnum::ARTSEN->value, 'name' => ResourceTypeEnum::ARTSEN->label()],
            ['external_id' => ResourceTypeEnum::OTHER->value, 'name' => ResourceTypeEnum::OTHER->label()],
            ['external_id' => ResourceTypeEnum::CARDIOLOGIE->value, 'name' => ResourceTypeEnum::CARDIOLOGIE->label()],
        ];

        foreach ($defaults as $data) {
            ResourceType::create($data);
        }
    }
}
