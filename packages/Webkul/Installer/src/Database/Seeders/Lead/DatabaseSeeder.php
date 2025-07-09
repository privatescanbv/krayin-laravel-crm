<?php

namespace Webkul\Installer\Database\Seeders\Lead;

use Database\Seeders\DepartmentSeeder;
use Database\Seeders\LeadChannelSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        $this->call(PipelineSeeder::class, false, ['parameters' => $parameters]);
        $this->call(TypeSeeder::class, false, ['parameters' => $parameters]);
        $this->call(SourceSeeder::class, false, ['parameters' => $parameters]);
        $this->call(LeadChannelSeeder::class, false, ['parameters' => $parameters]);
        $this->call(DepartmentSeeder::class, false, ['parameters' => $parameters]);
    }
}
