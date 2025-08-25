<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    public static function findHerniaId(): string
    {
        return Department::query()->where('name', 'Hernia')->firstOrFail()->id;
    }

    public static function findPrivateScanId(): string
    {
        return Department::query()->where('name', 'Privatescan')->firstOrFail()->id;
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
}
