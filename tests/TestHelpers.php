<?php

use App\Enums\Departments;
use App\Models\Department;
use Webkul\User\Models\Group;

if (! function_exists('ensureDepartmentsAndGroups')) {
    /**
     * Ensure departments and groups exist for testing.
     */
    function ensureDepartmentsAndGroups(): void
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

if (! function_exists('getDefaultGroup')) {
    /**
     * Get the default group (Privatescan).
     */
    function getDefaultGroup(): Group
    {
        ensureDepartmentsAndGroups();

        return Group::where('name', 'Privatescan')->firstOrFail();
    }
}

if (! function_exists('getHerniaGroup')) {
    /**
     * Get the Hernia group.
     */
    function getHerniaGroup(): Group
    {
        ensureDepartmentsAndGroups();

        return Group::where('name', 'Hernia')->firstOrFail();
    }
}

if (! function_exists('getPrivatescanDepartment')) {
    /**
     * Get the Privatescan department.
     */
    function getPrivatescanDepartment(): Department
    {
        ensureDepartmentsAndGroups();

        return Department::where('name', Departments::PRIVATESCAN->value)->firstOrFail();
    }
}

if (! function_exists('getHerniaDepartment')) {
    /**
     * Get the Hernia department.
     */
    function getHerniaDepartment(): Department
    {
        ensureDepartmentsAndGroups();

        return Department::where('name', Departments::HERNIA->value)->firstOrFail();
    }
}
