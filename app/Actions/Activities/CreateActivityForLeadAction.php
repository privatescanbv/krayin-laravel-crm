<?php

namespace App\Actions\Activities;

use App\Models\Department;
use Webkul\Activity\Contracts\Activity;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;

class CreateActivityForLeadAction extends AbstractCreateActivityAction
{
    public function __construct(
        ActivityRepository $activityRepository,
        private readonly LeadRepository $leadRepository,
    ) {
        parent::__construct($activityRepository);
    }

    /**
     * @throws DuplicateException
     */
    public function executeForId(string $leadId, bool $isDone, array $activityData): Activity
    {
        return $this->execute($this->leadRepository->findOrFail($leadId), $isDone, $activityData);
    }

    /**
     * @throws DuplicateException
     */
    public function execute(Lead $lead, bool $isDone, array $activityData): Activity
    {
        $activityData['lead_id'] = $lead->id;
        $groupId = Department::getGroupIdForLead($lead);

        return $this->createActivity('lead_id', $lead->id, $groupId, $isDone, $activityData);
    }
}
