<?php

namespace App\Services\Activities;

use App\Models\Department;
use Illuminate\Database\Query\Builder;

class ActivityDepartmentFilter
{
    /**
     * Join groups when filtering by activity user group department.
     */
    public static function applyJoins(Builder $query): void
    {
        if (! self::hasJoin($query, 'groups')) {
            $query->leftJoin('groups', 'activities.group_id', '=', 'groups.id');
        }
    }

    /**
     * Filter activities by the department of their assigned user group.
     */
    public static function applyToQuery(Builder $query, string $departmentName): void
    {
        $departmentId = Department::query()
            ->where('name', $departmentName)
            ->value('id');

        if (! $departmentId) {
            $query->whereRaw('0 = 1');

            return;
        }

        self::applyJoins($query);

        $query->where('groups.department_id', $departmentId);
    }

    private static function hasJoin(Builder $query, string $table): bool
    {
        $joins = $query->joins ?? [];

        foreach ($joins as $join) {
            if ($join->table === $table) {
                return true;
            }
        }

        return false;
    }
}
