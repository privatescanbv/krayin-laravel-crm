<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\Departments;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ActivityQueueRepository
{
    public function __construct(
        protected ActivityQueueRegistry $registry,
    ) {}

    /**
     * Get open and overdue counts for a given queue.
     *
     * @return array{open:int,overdue:int}
     */
    public function counts(string $queueKey, ?string $department = null): array
    {
        $baseQuery = DB::table('activities')
            ->leftJoin('leads', 'activities.lead_id', '=', 'leads.id')
            ->leftJoin('orders', 'activities.order_id', '=', 'orders.id')
            ->leftJoin('users', 'activities.user_id', '=', 'users.id')
            ->leftJoin('groups', 'activities.group_id', '=', 'groups.id')
            ->whereIn('type', [
                'call',
                'meeting',
                'task',
                ActivityType::PATIENT_MESSAGE->value,
                ActivityType::FILE->value,
            ]);

        // Apply ACL: same authorization logic as ActivityDataGrid.
        $user = auth()->guard('user')->user();
        if ($user && ! $user->isGlobalAdmin()) {
            $baseQuery->where(function ($query) {
                if ($userIds = bouncer()->getAuthorizedUserIds()) {
                    $query->whereIn('activities.user_id', $userIds)
                        ->orWhere(function ($query) use ($userIds) {
                            $query->whereNotNull('activities.group_id')
                                ->whereExists(function ($query) use ($userIds) {
                                    $query->select(DB::raw(1))
                                        ->from('user_groups')
                                        ->whereColumn('user_groups.group_id', 'activities.group_id')
                                        ->whereIn('user_groups.user_id', $userIds);
                                });
                        });
                }
            });
        }

        // Apply queue-specific filters.
        $this->registry->applyFilters($baseQuery, $queueKey, $user?->id);

        // Department filter — same logic as ActivityDataGrid.
        if ($department && in_array($department, Departments::allValues(), true)) {
            $baseQuery->where('groups.name', $department);
        }

        $openQuery = (clone $baseQuery)->where('activities.is_done', false);

        $now = Carbon::now();

        $overdueQuery = (clone $openQuery)
            ->whereNotNull('activities.schedule_to')
            ->where('activities.schedule_to', '<', $now);

        return [
            'open'    => $openQuery->count('activities.id'),
            'overdue' => $overdueQuery->count('activities.id'),
        ];
    }
}
