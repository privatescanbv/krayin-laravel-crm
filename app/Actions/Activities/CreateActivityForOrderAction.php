<?php

namespace App\Actions\Activities;

use App\Models\Department;
use App\Models\Order;
use Webkul\Activity\Contracts\Activity;
use Webkul\Activity\Repositories\ActivityRepository;

class CreateActivityForOrderAction extends AbstractCreateActivityAction
{
    public function __construct(
        ActivityRepository $activityRepository,
    ) {
        parent::__construct($activityRepository);
    }

    /**
     * @throws DuplicateException
     */
    public function execute(Order $order, bool $isDone, array $activityData): Activity
    {
        $activityData['order_id'] = $order->id;
        $groupId = Department::getGroupIdForLead($order->salesLead->lead);

        return $this->createActivity('order_id', $order->id, $groupId, $isDone, $activityData);
    }
}
