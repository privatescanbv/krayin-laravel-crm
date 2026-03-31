<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(array $parameters = [])
    {
        $this->call(FolderSeeder::class);
        $this->call(TestPipelineSeeder::class);
        $this->call(LeadSourceSeeder::class);
        $this->call(LeadTypeSeeder::class);
        $this->call(LeadChannelSeeder::class);
        $this->call(DutchLocaleSeeder::class);
        $this->call(DepartmentSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(ClinicSeeder::class);
        $this->call(ClinicDepartmentSeeder::class);
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
