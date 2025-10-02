<?php

use App\Enums\Departments;
use App\Models\Department;
use Illuminate\Database\Migrations\Migration;
use Webkul\User\Models\Group;

return new class extends Migration
{
    public function up(): void
    {
        // Create mapping based on enum values and group names
        $mappings = [
            'Hernia'      => Departments::HERNIA->value,        // Group "Hernia" -> Department "Herniapoli"
            'Privatescan' => Departments::PRIVATESCAN->value, // Group "Privatescan" -> Department "Privatescan"
        ];

        foreach ($mappings as $groupName => $departmentName) {
            $department = Department::where('name', $departmentName)->first();
            if ($department) {
                Group::where('name', $groupName)->update(['department_id' => $department->id]);
            }
        }
    }

    public function down(): void
    {
        Group::query()->update(['department_id' => null]);
    }
};
