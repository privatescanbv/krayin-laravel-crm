<?php

namespace App\Actions\Activities;

use App\Models\Department;
use App\Models\Order;
use App\Models\SalesLead;
use Exception;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Contracts\Activity;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;

/**
 * - remove uncompleted GVL forms
 * - remove uncompleted orders
 */
class CreateActivityForLeadOrSalesAction
{
    public function __construct(
        private readonly LeadRepository $leadRepository,
        private readonly ActivityRepository $activityRepository,
    ) {}

    /**
     * @throws DuplicateException
     */
    public function executeForLeadId(string $leadId, bool $activityIsDone, array $activityData): Activity
    {
        return $this->executeForLead(
            $this->leadRepository->findOrFail($leadId),
            $activityIsDone,
            $activityData
        );
    }

    /**
     * @throws DuplicateException
     */
    public function executeForSales(SalesLead $sales, bool $activityIsDone, array $activityData): Activity
    {
        $groupId = Department::getGroupIdForLead($sales->lead);
        $activityData['sales_lead_id'] = $sales->id;

        $isDuplicate = $this->activityRepository
            ->where('sales_lead_id', $sales->id)
            ->where('title', $activityData['title'] ?? null)
            ->where('is_done', 0)
            ->exists();

        if ($isDuplicate) {
            throw new DuplicateException('Duplicate activity: same title exists for this sales lead and is not done.');
        }

        return $this->activityRepository->create(array_merge($activityData, [
            'is_done'  => $activityIsDone ? 1 : 0,
            'user_id'  => $activityData['user_id'] ?? null,
            'group_id' => $groupId,
        ]));
    }

    /**
     * @throws DuplicateException
     */
    public function executeForOrder(Order $order, bool $activityIsDone, array $activityData): Activity
    {
        $groupId = Department::getGroupIdForLead($order->salesLead->lead);
        $activityData['order_id'] = $order->id;

        $isDuplicate = $this->activityRepository
            ->where('order_id', $order->id)
            ->where('title', $activityData['title'] ?? null)
            ->where('is_done', 0)
            ->exists();

        if ($isDuplicate) {
            throw new DuplicateException('Duplicate activity: same title exists for this order and is not done.');
        }

        return $this->activityRepository->create(array_merge($activityData, [
            'is_done'  => $activityIsDone ? 1 : 0,
            'user_id'  => $activityData['user_id'] ?? null,
            'group_id' => $groupId,
        ]));
    }

    /**
     * @throws DuplicateException
     */
    public function executeForLead(Lead $lead, bool $activityIsDone, array $activityData): Activity
    {
        $leadId = $lead->id;
        $groupId = Department::getGroupIdForLead($lead);
        $activityData['lead_id'] = $leadId;

        return $this->createActivity($leadId, null, $groupId, $activityIsDone, $activityData);

    }

    private function createActivity(
        ?string $leadId,
        ?string $salesId,
        int $groupId,
        bool $activityIsDone,
        array $activityData
    ): Activity {
        if (is_null($leadId) && is_null($salesId)) {
            throw new Exception('LeadId or SalesId is required');
        }

        // Duplicate guard: same title on same lead with is_done = 0 should be rejected
        $isDuplicate = $this->activityRepository
            ->where('lead_id', $leadId)
            ->where('title', $activityData['title'] ?? null)
            ->where('is_done', 0)
            ->exists();

        if ($isDuplicate) {
            Log::warning('Lead activities store: duplicate detected', [
                'lead_id' => $leadId,
                'title'   => $activityData['title'] ?? null,
            ]);

            throw new DuplicateException('Duplicate activity: same title exists for this lead and is not done.');
        }

        return $this->activityRepository->create(array_merge($activityData, [
            'is_done'  => $activityIsDone ? 1 : 0,
            'user_id'  => $activityData['user_id'] ?? null,
            'group_id' => $groupId,
        ]));
    }
}
