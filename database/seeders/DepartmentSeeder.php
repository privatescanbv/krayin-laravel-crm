<?php

namespace Database\Seeders;

use App\Enums\Departments;
use App\Models\Department;
use Carbon\Carbon;

class DepartmentSeeder extends BaseSeeder
{
    public function run(array $parameters = []): void
    {
        $departments = Departments::allValues();
        foreach ($departments as $name) {
            Department::firstOrCreate(
                ['name' => $name],
                [
                    'name' => $name,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]
            );
        }
    }
}
