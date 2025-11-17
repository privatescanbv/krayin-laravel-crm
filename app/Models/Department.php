<?php

namespace App\Models;

use App\Enums\Departments;
use App\Traits\HasAuditTrail;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Group;

class Department extends Model
{
    use HasAuditTrail, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
    ];

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
     *
     * @throws Exception if lead has no department or no group found
     */
    public static function getGroupIdForLead(Lead $lead): int
    {
        if (! $lead->department_id) {
            logger()->warning("Lead {$lead->id} has no department_id, defaulting to Privatescan group");
            // Resolve Privatescan department's default group id
            $privatescanDepartmentId = self::findPrivateScanId();

            return Group::query()
                ->where('department_id', $privatescanDepartmentId)
                ->value('id');
        }

        // Find group by department_id for efficient lookup
        return Group::query()
            ->select(['id'])
            ->where('department_id', $lead->department_id)
            ->firstOrFail()
            ->id;
    }

    /**
     * Get the groups that belong to this department.
     */
    public function groups()
    {
        return $this->hasMany(Group::class, 'department_id');
    }

    public function isHernia(): bool
    {
        return $this->name === Departments::HERNIA->value;
    }
}
