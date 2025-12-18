<?php

namespace Database\Seeders;

use App\Enums\ResourceType as ResourceTypeEnum;
use App\Models\ResourceType;

class ResourceTypeSeeder extends BaseSeeder
{
    public function run(): void
    {
        $this->truncateTables(['resource_types']);
        foreach (ResourceTypeEnum::cases() as $case) {

            ResourceType::create(['name' => $case->label()]);
        }
    }
}
