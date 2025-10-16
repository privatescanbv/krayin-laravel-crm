<?php

namespace Webkul\Activity\Repositories;

use Exception;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Services\ViewService;
use Webkul\Core\Eloquent\Repository;
use App\Enums\ActivityType;
use App\Models\SalesLead;
use Webkul\Lead\Models\Lead;

class ActivityRepository extends Repository
{
    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        protected FileRepository $fileRepository,
        Container                $container
    )
    {
        parent::__construct($container);
    }

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Webkul\Activity\Contracts\Activity';
    }

    /**
     * Create pipeline.
     *
     * @return \Webkul\Activity\Contracts\Activity
     */
    public function create(array $data)
    {
        $activity = parent::create($data);

        if (isset($data['file'])) {
            $this->fileRepository->create([
                'name' => $data['name'] ?? $data['file']->getClientOriginalName(),
                'path' => $data['file']->store('activities/' . $activity->id),
                'activity_id' => $activity->id,
            ]);
        }

        // Participants functionality removed - using only user_id for assignment

        return $activity;
    }

    /**
     * Update pipeline.
     *
     * @param int $id
     * @param string $attribute
     * @return \Webkul\Activity\Contracts\Activity
     */
    public function update(array $data, $id, $attribute = 'id')
    {
        // Convert empty lead_id to null for foreign key constraint
        if (isset($data['lead_id']) && $data['lead_id'] === '') {
            $data['lead_id'] = null;
        }

        $activity = parent::update($data, $id);

        // Participants functionality removed - using only user_id for assignment

        return $activity;
    }

    /**
     * @param string $dateRange
     * @param string|null $view
     * @return mixed
     */
    public function getActivities($dateRange, $view = null)
    {
        $query = $this->select(
            'activities.id',
            'activities.created_at',
            'activities.title',
            'activities.schedule_from as start',
            'activities.schedule_to as end',
            DB::raw("CONCAT(users.first_name, ' ', users.last_name) as user_name"),
        )
            ->addSelect(DB::raw('IF(activities.is_done, "done", "") as class'))
            ->leftJoin('users', 'activities.user_id', '=', 'users.id')
            ->leftJoin('groups', 'activities.group_id', '=', 'groups.id')
            ->whereIn('type', ['call', 'meeting', 'task', 'lunch'])
            ->whereBetween('activities.schedule_from', $dateRange)
            ->where(function ($query) {
                if ($userIds = bouncer()->getAuthorizedUserIds()) {
                    $query->whereIn('activities.user_id', $userIds)
                        ->orWhereHas('group', function ($query) use ($userIds) {
                            $query->whereHas('users', function ($query) use ($userIds) {
                                $query->whereIn('users.id', $userIds);
                            });
                        });
                }
            });

        // Apply view filters - use default view if none specified
        $viewService = app(ViewService::class);
        if (!$view) {
            $defaultView = $viewService->getDefaultView();
            $view = $defaultView['key'];
        }
        $query = $viewService->applyViewFilters($query, $view);

        return $query->distinct()->get();
    }

    /**
     * @param string $startFrom
     * @param string $endFrom
     * @param array $participants
     * @param int $id
     * @return bool
     */
    public function isDurationOverlapping($startFrom, $endFrom, $participants, $id)
    {
        // Simplified overlap detection - only check assigned user conflicts
        $queryBuilder = $this->where(function ($query) use ($startFrom, $endFrom) {
            $query->where([
                ['activities.schedule_from', '<=', $startFrom],
                ['activities.schedule_to', '>=', $startFrom],
            ])->orWhere([
                ['activities.schedule_from', '>=', $startFrom],
                ['activities.schedule_from', '<=', $endFrom],
            ]);
        })
            ->whereNotNull('user_id'); // Only check activities with assigned users

        if (!is_null($id)) {
            $queryBuilder->where('activities.id', '!=', $id);
        }

        return $queryBuilder->count() ? true : false;
    }

    public function unassign(Activity $activity): void
    {
        $activity->update(['user_id' => null]);
        $activity->save();
    }

    public function createSystemActivitiesForSalesLeadCreation(Lead $lead, SalesLead $salesLead): void
    {
        $this->createSystemActivityForSalesLeadCreation($lead, $salesLead);
        $this->createSystemActivityForLeadCreation($lead, $salesLead);
    }

    /**
     * Create a system activity on a lead that links to the created sales lead view.
     */
    public function createSystemActivityForSalesLeadCreation(Lead $lead, SalesLead $salesLead): ?Activity
    {
        try {
            Log::info('Creating system activity for sales lead creation', [
                'lead_id' => $lead->id,
                'sales_lead_id' => $salesLead->id,
            ]);

            $link = route('admin.sales-leads.view', $salesLead->id);
            $activity = $this->create([
                'type' => ActivityType::SYSTEM,
                'title' => 'Sales lead aangemaakt',
                'comment' => null,
                'is_done' => 1,
                'user_id' => auth()->check() ? auth()->id() : null,
                'lead_id' => $lead->id,
                'sales_lead_id' => $salesLead->id,
                'additional' => [
                    'old' => ['label' => 'Leeg'],
                    'new' => ['label' => $salesLead->name],
                    'link' => $link,
                    'link_label' => 'Bekijk sales lead',
                    'sales_lead' => [
                        'id' => $salesLead->id,
                        'name' => $salesLead->name,
                    ],
                ],
            ]);

            Log::info('System activity created successfully', [
                'activity_id' => $activity->id,
                'lead_id' => $lead->id,
                'sales_lead_id' => $salesLead->id,
            ]);

            return $activity;
        } catch (Throwable $e) {
            Log::error('Failed to create system activity for sales lead creation', [
                'lead_id' => $lead->id,
                'sales_lead_id' => $salesLead->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create a system activity on a lead that links to the created sales lead view.
     */
    public function createSystemActivityForLeadCreation(Lead $lead, SalesLead $salesLead): ?Activity
    {
        try {
            return $this->create([
                'type' => ActivityType::SYSTEM,
                'title' => 'Sales lead aangemaakt vanuit lead',
                'comment' => null,
                'is_done' => 1,
                'user_id' => auth()->check() ? auth()->id() : null,
                'sales_lead_id' => $salesLead->id,
                'additional' => [
                    'old' => ['label' => 'Leeg'],
                    'new' => ['label' => $lead->name],
                    'link' => route('admin.leads.view', $lead->id),
                    'link_label' => 'Bekijk lead',
                    'lead' => [
                        'id' => $lead->id,
                        'name' => $lead->name,
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to create system activity for sales lead creation in sales lead', [
                'lead_id' => $lead->id,
                'sales_lead_id' => $salesLead->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }
}
