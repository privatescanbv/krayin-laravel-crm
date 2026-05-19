<?php

namespace App\Services\Activities;

use Illuminate\Database\Query\Builder;

class ActivityDepartmentFilter
{
    /**
     * Join tables needed to resolve entity department for operational-dashboard filtering.
     */
    public static function applyJoins(Builder $query): void
    {
        if (! self::hasJoin($query, 'groups')) {
            $query->leftJoin('groups', 'activities.group_id', '=', 'groups.id');
        }

        if (! self::hasJoin($query, 'leads')) {
            $query->leftJoin('leads', 'activities.lead_id', '=', 'leads.id');
        }

        if (! self::hasJoin($query, 'orders')) {
            $query->leftJoin('orders', 'activities.order_id', '=', 'orders.id');
        }

        if (! self::hasJoin($query, 'activity_salesleads')) {
            $query->leftJoin('salesleads as activity_salesleads', function ($join): void {
                $join->on('activities.sales_lead_id', '=', 'activity_salesleads.id')
                    ->orOn('orders.sales_lead_id', '=', 'activity_salesleads.id');
            });
        }

        if (! self::hasJoin($query, 'activity_lead_dept')) {
            $query->leftJoin('departments as activity_lead_dept', 'leads.department_id', '=', 'activity_lead_dept.id');
        }

        if (! self::hasJoin($query, 'saleslead_dept')) {
            $query->leftJoin('departments as saleslead_dept', 'activity_salesleads.department_id', '=', 'saleslead_dept.id');
        }

        if (! self::hasJoin($query, 'saleslead_leads')) {
            $query->leftJoin('leads as saleslead_leads', 'activity_salesleads.lead_id', '=', 'saleslead_leads.id');
        }

        if (! self::hasJoin($query, 'saleslead_lead_dept')) {
            $query->leftJoin('departments as saleslead_lead_dept', 'saleslead_leads.department_id', '=', 'saleslead_lead_dept.id');
        }
    }

    /**
     * Filter activities by department using entity department (aligned with SalesLead::getDepartment()).
     */
    public static function applyToQuery(Builder $query, string $departmentName): void
    {
        self::applyJoins($query);

        $query->where(function (Builder $departmentQuery) use ($departmentName): void {
            $departmentQuery
                ->where(function (Builder $salesOrOrderQuery) use ($departmentName): void {
                    $salesOrOrderQuery
                        ->where(function (Builder $entityQuery): void {
                            $entityQuery
                                ->whereNotNull('activities.sales_lead_id')
                                ->orWhereNotNull('activities.order_id');
                        })
                        ->whereRaw(
                            'COALESCE(saleslead_dept.name, saleslead_lead_dept.name) = ?',
                            [$departmentName]
                        );
                })
                ->orWhere(function (Builder $leadQuery) use ($departmentName): void {
                    $leadQuery
                        ->whereNotNull('activities.lead_id')
                        ->whereNull('activities.sales_lead_id')
                        ->whereNull('activities.order_id')
                        ->where('activity_lead_dept.name', $departmentName);
                })
                ->orWhere(function (Builder $fallbackQuery) use ($departmentName): void {
                    $fallbackQuery
                        ->whereNull('activities.lead_id')
                        ->whereNull('activities.sales_lead_id')
                        ->whereNull('activities.order_id')
                        ->where('groups.name', $departmentName);
                });
        });
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
