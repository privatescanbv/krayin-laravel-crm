<?php

namespace Database\Seeders;

use App\Enums\Departments;
use App\Models\Department;
use Webkul\Installer\Database\Seeders\Attribute\AttributeSeeder;
use Webkul\Installer\Database\Seeders\Lead\PipelineSeeder;
use Webkul\Installer\Database\Seeders\Lead\SourceSeeder;
use Webkul\Installer\Database\Seeders\Lead\TypeSeeder;
use Webkul\User\Models\Group;
use Illuminate\Support\Facades\DB;

class TestSeeder extends BaseSeeder
{
    public function run($parameters = []): void
    {
        // Only run seeders if tables are empty to avoid duplicates
        $this->runSeedersIfEmpty();
        
        // Ensure departments and groups are properly linked for testing
        $this->ensureDepartmentsAndGroups();
    }

    /**
     * Run seeders only if the relevant tables are empty to avoid duplicate entries
     */
    private function runSeedersIfEmpty(): void
    {
        // Check if lead_pipelines table is empty
        if (DB::table('lead_pipelines')->count() === 0) {
            $this->call([PipelineSeeder::class]);
        }
        
        // Check if attributes table is empty
        if (DB::table('attributes')->count() === 0) {
            $this->call([AttributeSeeder::class]);
        }
        
        // Check if departments table is empty
        if (DB::table('departments')->count() === 0) {
            $this->call([DepartmentSeeder::class]);
        }
        
        // Check if lead_types table is empty
        if (DB::table('lead_types')->count() === 0) {
            $this->call([TypeSeeder::class]);
        }
        
        // Check if lead_sources table is empty
        if (DB::table('lead_sources')->count() === 0) {
            $this->call([SourceSeeder::class]);
        }
        
        // Check if resource_types table is empty
        if (DB::table('resource_types')->count() === 0) {
            $this->call([ResourceTypeSeeder::class]);
        }
        
        // Check if clinics table is empty
        if (DB::table('clinics')->count() === 0) {
            $this->call([ClinicSeeder::class]);
        }
    }

    /**
     * Ensure departments and groups exist and are properly linked for testing.
     */
    private function ensureDepartmentsAndGroups(): void
    {
        // Create departments if they don't exist
        $departments = [];
        foreach (Departments::cases() as $dept) {
            $departments[$dept->value] = Department::firstOrCreate(['name' => $dept->value]);
        }

        // Create groups linked to departments
        $groupMappings = [
            'Hernia'      => $departments[Departments::HERNIA->value]->id,
            'Privatescan' => $departments[Departments::PRIVATESCAN->value]->id,
        ];

        foreach ($groupMappings as $groupName => $departmentId) {
            Group::firstOrCreate(
                ['name' => $groupName],
                ['department_id' => $departmentId]
            );
        }
    }
}
