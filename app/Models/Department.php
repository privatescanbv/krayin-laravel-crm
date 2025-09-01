<?php

namespace App\Models;

use App\Enums\Departments;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    /**
     * Get the groups that belong to this department.
     */
    public function groups()
    {
        return $this->hasMany(\Webkul\User\Models\Group::class, 'department_id');
    }

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
    public static function mapGroupToDepartmentId(array $groupNames): int
    {
        if (empty($groupNames)) {
            return self::findPrivateScanId();
        }
        // Convert group names to lowercase for case-insensitive comparison
        $lowerGroupNames = array_map('strtolower', $groupNames);

        // Priority logic: if user has both hernia and privatescan, choose privatescan
        if (in_array('privatescan', $lowerGroupNames)) {
            try {
                return self::findPrivateScanId();
            } catch (Exception $e) {
                // If Privatescan department not found, continue to other mappings
            }
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
     * Get group_id for a lead based on its department.
     * Uses the department_id relationship for efficient mapping.
     *
     * @param  \Webkul\Lead\Models\Lead  $lead  The lead to get group_id for
     * @return int The group ID
     * @throws Exception if lead has no department or no group found
     */
    public static function getGroupIdForLead($lead): int
    {
        if (!$lead) {
            throw new Exception("Lead cannot be null");
        }

        if (!$lead->department_id) {
            throw new Exception("Lead {$lead->id} has no department_id");
        }

        // Find group by department_id for efficient lookup
        $group = \Webkul\User\Models\Group::query()
            ->where('department_id', $lead->department_id)
            ->first();

        if (!$group) {
            throw new Exception("No group found for department_id: {$lead->department_id} (lead: {$lead->id})");
        }

        return $group->id;
    }

    /**
     * Map department name to group ID.
     * Uses the Departments enum to validate and map to corresponding group.
     *
     * @param  string  $departmentName  The name of the department
     * @return int The group ID
     * @throws Exception if department or group not found
     */
    public static function mapDepartmentToGroupId(string $departmentName): int
    {
        // Validate that department name exists in enum
        $validDepartments = Departments::allValues();
        if (!in_array($departmentName, $validDepartments)) {
            throw new Exception("Invalid department name: {$departmentName}. Must be one of: " . implode(', ', $validDepartments));
        }

        // Find department first, then get its group
        $department = self::query()->where('name', $departmentName)->first();
        if (!$department) {
            throw new Exception("Department not found: {$departmentName}");
        }

        $group = \Webkul\User\Models\Group::query()
            ->where('department_id', $department->id)
            ->first();

        if (!$group) {
            throw new Exception("No group found for department: {$departmentName}");
        }

        return $group->id;
    }
}
