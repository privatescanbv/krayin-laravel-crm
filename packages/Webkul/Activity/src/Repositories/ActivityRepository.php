<?php

namespace Webkul\Activity\Repositories;

use App\Helpers\DatabaseHelper;
use App\Models\Clinic;
use App\Models\Order;
use Illuminate\Container\Container;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Models\File as ActivityFile;
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
        Activity::normalizeForeignKeys($data);

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
        // Always use the authenticated user — never trust request input for user_id,
        // as that would allow any user to probe another user's schedule.
        $userId = auth()->guard('user')->id();

        if (! $userId) {
            return false;
        }

        // Only check open meetings for the same user with a true time overlap
        $queryBuilder = $this->where('type', 'meeting')
            ->where('user_id', $userId)
            ->where('is_done', false)
            ->where('schedule_from', '<', $endFrom)
            ->where('schedule_to', '>', $startFrom);

        if (! is_null($id)) {
            $queryBuilder->where('activities.id', '!=', $id);
        }

        return $queryBuilder->exists();
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

    public function countOpen(object $entity): \Illuminate\Http\JsonResponse
    {
        $count = match (true) {
            $entity instanceof Person    => $this->countOpenForPerson($entity),
            $entity instanceof Lead      => $this->countOpenForSimple($entity, 'lead_id'),
            $entity instanceof SalesLead => $this->countOpenForSalesLead($entity),
            $entity instanceof Clinic    => $this->countOpenForSimple($entity, 'clinic_id'),
            $entity instanceof Order     => $this->countOpenForOrder($entity),
            default => throw new InvalidArgumentException(
                'Unsupported entity type for counting open activities: ' . get_class($entity)
            ),
        };

        return response()->json(['data' => $count]);
    }

    /** Lead, Clinic — one FK on both activities and emails. */
    private function countOpenForSimple(object $entity, string $fk): int
    {
        return Activity::where($fk, $entity->id)->where('is_done', 0)->count()
             + Email::where($fk, $entity->id)->where('is_read', 0)->count();
    }

    /** SalesLead — own activities/emails + child order activities (hierarchy). */
    private function countOpenForSalesLead(SalesLead $entity): int
    {
        $count = Activity::where('sales_lead_id', $entity->id)->where('is_done', 0)->count()
               + Email::where('sales_lead_id', $entity->id)->where('is_read', 0)->count();

        $orderIds = $entity->orders()->pluck('orders.id');
        if ($orderIds->isNotEmpty()) {
            $count += Activity::whereIn('order_id', $orderIds)->where('is_done', 0)->count();
        }

        return $count;
    }

    /** Order — activities only; emails table has no order_id column. */
    private function countOpenForOrder(Order $entity): int
    {
        return Activity::where('order_id', $entity->id)->where('is_done', 0)->count();
    }

    /** Person — own FK/pivot activities + emails, plus child Lead/SalesLead/Order hierarchy. */
    private function countOpenForPerson(Person $entity): int
    {
        $count = Activity::where('person_id', $entity->id)->where('is_done', 0)->count()
               + Email::where('person_id', $entity->id)->where('is_read', 0)->count();

        // Child Leads (via lead_persons pivot)
        $leadIds = DB::table('lead_persons')->where('person_id', $entity->id)->pluck('lead_id');
        if ($leadIds->isNotEmpty()) {
            $count += Activity::whereIn('lead_id', $leadIds)->where('is_done', 0)->count()
                    + Email::whereIn('lead_id', $leadIds)->where('is_read', 0)->count();
        }

        // Child SalesLeads (via saleslead_persons pivot)
        $salesLeadIds = DB::table('saleslead_persons')->where('person_id', $entity->id)->pluck('saleslead_id');
        if ($salesLeadIds->isNotEmpty()) {
            $count += Activity::whereIn('sales_lead_id', $salesLeadIds)->where('is_done', 0)->count()
                    + Email::whereIn('sales_lead_id', $salesLeadIds)->where('is_read', 0)->count();

            // Child Orders of those SalesLeads
            $orderIds = Order::whereIn('sales_lead_id', $salesLeadIds)->pluck('id');
            if ($orderIds->isNotEmpty()) {
                $count += Activity::whereIn('order_id', $orderIds)->where('is_done', 0)->count();
            }
        }

        return $count;
    }

    /**
     * Paginate "document" files for the given order ids.
     *
     * Documents are stored as activities (type=file) with records in activity_files.
     *
     * @param  array<int>  $orderIds
     */
    public function paginateDocumentFilesForOrders(array $orderIds, int $perPage, ?string $documentType = null): LengthAwarePaginator
    {
        return ActivityFile::query()
            ->with(['activity'])
            ->whereHas('activity', function ($q) use ($orderIds, $documentType) {
                $q->where('type', ActivityType::FILE->value)
                    ->where('publish_to_portal', true)
                    ->whereIn('order_id', $orderIds);

                if (is_string($documentType) && $documentType !== '') {
                    $q->where('additional->document_type', $documentType);
                }
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
