<?php

namespace Database\Seeders;

use Webkul\Installer\Database\Seeders\Attribute\AttributeSeeder;
use Webkul\Installer\Database\Seeders\Lead\PipelineSeeder;
use Webkul\Installer\Database\Seeders\Lead\SourceSeeder;
use Webkul\Installer\Database\Seeders\Lead\TypeSeeder;

class TestSeeder extends BaseSeeder
{
    public function run(): void
    {
        $this->call([
            PipelineSeeder::class,
            AttributeSeeder::class,
            DepartmentSeeder::class,
            TypeSeeder::class,
            SourceSeeder::class,
        ]);
    }
}
