<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Webkul\Installer\Database\Seeders\DatabaseSeeder as KrayinDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(array $parameters = [])
    {
        $this->call(KrayinDatabaseSeeder::class);
        $this->call(DutchLocaleSeeder::class);
        $this->call(ClinicSeeder::class);
        $this->call(ResourceTypeSeeder::class);
        $this->call(ProductTypeSeeder::class);
        $this->call(ProductGroupSeeder::class);
        $this->call(ResourceSeeder::class);
        $this->call(ProductSeeder::class);
        $this->call(CampaignSeeder::class);
        $this->call(PartnerProductSeeder::class);
        $this->call(WorkflowSeeder::class);
    }
}
