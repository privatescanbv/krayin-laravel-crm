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
        // Use the order's pipeline department so that tasks are always assigned to the
        // correct group, even when the lead was originally from a different department
        // (e.g. Herniapoli lead converted to Privatescan).
        $department = $order->getPipelineDepartment();
        $groupId = Department::getGroupIdForDepartmentId($department->id);

        return $this->createActivity('order_id', $order->id, $groupId, $isDone, $activityData);
    }
}
