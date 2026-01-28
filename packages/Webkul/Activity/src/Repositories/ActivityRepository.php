<?php

namespace Webkul\Activity\Repositories;

use App\Helpers\DatabaseHelper;
use App\Models\Clinic;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Services\ViewService;
use Webkul\Contact\Models\Person;
use Webkul\Core\Eloquent\Repository;
use App\Enums\ActivityType;
use App\Enums\PortalRevocationReason;
use App\Models\SalesLead;
use Webkul\Email\Models\Email;
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

    public function getActivities(array $dateRange, ?string $view = null)
    {
        $query = $this->select(
            'activities.id',
            'activities.created_at',
            'activities.title',
            'activities.schedule_from as start',
            'activities.schedule_to as end',
            DB::raw(DatabaseHelper::concatUserName('users.', 'user_name')),
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
        $this->createSystemActivityForLeadCreation($lead, $salesLead);
        $this->createSystemActivityForSalesCreation($lead, $salesLead);
    }

    /**
     * Create a system activity on a lead that links to the created sales view.
     */
    public function createSystemActivityForLeadCreation(Lead $lead, SalesLead $salesLead): ?Activity
    {
        try {
            $link = route('admin.sales-leads.view', $salesLead->id);
            $activity = $this->create([
                'type' => ActivityType::SYSTEM,
                'title' => 'Sales aangemaakt',
                'comment' => null,
                'is_done' => 1,
                'user_id' => auth()->check() ? auth()->id() : null,
                'lead_id' => $lead->id,
                'additional' => [
                    'old' => ['label' => 'Leeg'],
                    'new' => ['label' => $salesLead->name],
                    'link' => $link,
                    'link_label' => 'Bekijk sales',
                    'sales_lead' => [
                        'id' => $salesLead->id,
                        'name' => $salesLead->name,
                    ],
                ],
            ]);

            return $activity;
        } catch (Throwable $e) {
            Log::error('Failed to create system activity for sales creation', [
                'lead_id' => $lead->id,
                'sales_lead_id' => $salesLead->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create a system activity on a lead that links to the created sales view.
     */
    public function createSystemActivityForSalesCreation(Lead $lead, SalesLead $salesLead): ?Activity
    {
        try {
            return $this->create([
                'type' => ActivityType::SYSTEM,
                'title' => 'Sales aangemaakt vanuit lead',
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
            Log::error('Failed to create system activity for sales creation', [
                'lead_id' => $lead->id,
                'sales_lead_id' => $salesLead->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Create a system activity when a patient portal account is revoked.
     */
    public function createSystemActivityForPortalRevocation(
        Person $person,
        PortalRevocationReason $reason,
        ?string $comment = null
    ): ?Activity {
        try {
            $reasonText = $reason->label();
            if ($comment) {
                $reasonText .= ': ' . $comment;
            }

            $activity = $this->create([
                'type' => ActivityType::SYSTEM,
                'title' => 'Patiëntportaal account ingetrokken',
                'comment' => $reasonText,
                'is_done' => 1,
                'user_id' => auth()->check() ? auth()->id() : null,
                'person_id' => $person->id,
                'additional' => [
                    'old' => ['label' => 'Actief'],
                    'new' => ['label' => 'Ingetrokken'],
                    'revocation_reason' => $reason->value,
                    'revocation_reason_label' => $reason->label(),
                    'revocation_comment' => $comment,
                    'person' => [
                        'id' => $person->id,
                        'name' => $person->name,
                    ],
                ],
            ]);

            $activity->persons()->attach($person->id);

            return $activity;
        } catch (Throwable $e) {
            Log::error('Failed to create system activity for portal revocation', [
                'person_id' => $person->id,
                'reason' => $reason->value,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function countOpen(Object $entity)
    {
        if($entity instanceof Lead) {
            $relationKey = 'lead_id';
        } else if($entity instanceof Clinic) {
            $relationKey = 'clinic_id';
        } else if($entity instanceof SalesLead) {
            $relationKey = 'sales_lead_id';
        } else if($entity instanceof Person) {
            $count = $entity->activities->where('is_done', 0)->count();
            $unreadEmail = Email::where('person_id', $entity->id)
                ->where('is_read', 0)
                ->count();
            return response()->json([
                'data' => $count + $unreadEmail,
            ]);
        } else {
            throw new InvalidArgumentException('Unsupported entity type for counting open activities.'.get_class($entity));
        }
        $entityId = $entity->id;
        $count = $this
            ->where($relationKey, $entityId)
            ->where('is_done', 0)
            ->count();
        $unreadEmail = Email::where($relationKey, $entityId)
            ->where('is_read', 0)
            ->count();
        return response()->json([
            'data' => $count + $unreadEmail,
        ]);
    }
}
