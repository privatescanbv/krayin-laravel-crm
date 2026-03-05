<?php

namespace App\Actions\Activities;

use App\Models\Department;
use App\Models\SalesLead;
use Webkul\Activity\Contracts\Activity;
use Webkul\Activity\Repositories\ActivityRepository;

class CreateActivityForSalesLeadAction extends AbstractCreateActivityAction
{
    public function __construct(
        ActivityRepository $activityRepository,
    ) {
        parent::__construct($activityRepository);
    }

    /**
     * @throws DuplicateException
     */
    public function execute(SalesLead $sales, bool $isDone, array $activityData): Activity
    {
        $activityData['sales_lead_id'] = $sales->id;
        $groupId = Department::getGroupIdForLead($sales->lead);

        return $this->createActivity('sales_lead_id', $sales->id, $groupId, $isDone, $activityData);
    }
}
