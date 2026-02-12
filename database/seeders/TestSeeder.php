<?php

namespace Database\Seeders;

use App\Enums\Departments;
use App\Models\Department;
use Webkul\Installer\Database\Seeders\Attribute\AttributeSeeder;
use Webkul\Installer\Database\Seeders\Lead\PipelineSeeder;
use Webkul\Installer\Database\Seeders\Lead\SourceSeeder;
use Webkul\Installer\Database\Seeders\Lead\TypeSeeder;
use Webkul\User\Models\Group;

class TestSeeder extends BaseSeeder
{
    public function run($parameters = []): void
    {
        $this->call([
            PipelineSeeder::class,
            AttributeSeeder::class,
            DepartmentSeeder::class,
            TypeSeeder::class,
            SourceSeeder::class,
            ResourceTypeSeeder::class,
            ClinicSeeder::class,
            RoleSeeder::class,
            UserTestSeeder::class,
        ], $parameters);

        // Ensure departments and groups are properly linked for testing
        $this->ensureDepartmentsAndGroups();
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
