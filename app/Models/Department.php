<?php

namespace App\Models;

use App\Enums\Departments;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\User\Models\Group;

class Department extends Model
{
    use HasFactory;

    public static function findHerniaId(): int
    {
        return Department::query()->where('name', Departments::HERNIA->value)->firstOrFail()->id;
    }

    public static function findPrivateScanId(): int
    {
        return Department::query()->where('name', Departments::PRIVATESCAN->value)->firstOrFail()->id;
    }

    /**
     * Map user groups to department IDs based on business logic.
     * If user belongs to both 'hernia' and 'privatescan', choose 'privatescan'.
     * If user belongs to 'privatescan', choose 'privatescan' department.
     * Otherwise, choose based on the first matching group.
     *
     * @param  array  $groupNames  Array of group names the user belongs to, if empty means allow in all groups
     *
     * @throws Exception with unexpect database data.
     */
    public static function mapGroupToDepartmentId(array $groupNames): string
    {
        if (empty($groupNames)) {
            throw new Exception("Could not map group to department, group names is empty");
        }
        // Convert group names to lowercase for case-insensitive comparison
        $lowerGroupNames = array_map('strtolower', $groupNames);

        // Priority logic: if user has both hernia and privatescan, choose privatescan
        if (in_array(strtolower(Departments::PRIVATESCAN->value), $lowerGroupNames)) {
            return self::findPrivateScanId();
        }

        // Map other groups to departments
        $groupToDepartmentMap = [
            'hernia' => 'Hernia',
            // Add more mappings as needed
        ];

        foreach ($lowerGroupNames as $groupName) {
            if (isset($groupToDepartmentMap[$groupName])) {
                try {
                    $department = Department::query()
                        ->where('name', $groupToDepartmentMap[$groupName])
                        ->first();

                    if ($department) {
                        return $department->id;
                    }
                } catch (Exception $e) {
                    throw new Exception('Error finding department for group '.$groupName.': '.$e->getMessage());
                }
            }
        }
        throw new Exception('Group not found');
    }

    /**
     * Map department to group ID based on business logic.
     * This is the reverse of mapGroupToDepartmentId.
     *
     * @param  Department  $department
     * @return int|null
     */
    public static function mapDepartmentToGroupId(Department $department): ?int
    {
        // Direct mapping: department name to group name
        $group = Group::where('name', $department->name)->first();

        if ($group) {
            return $group->id;
        }

        // Fallback mappings based on business logic
        $departmentToGroupMap = [
            'Hernia' => 'hernia',
            'Privatescan' => 'privatescan',
            // Add more mappings as needed
        ];

        if (isset($departmentToGroupMap[$department->name])) {
            $group = Group::where('name', $departmentToGroupMap[$department->name])->first();
            if ($group) {
                return $group->id;
            }
        }

        return null;
    }
}
