<?php

namespace Webkul\Admin\Http\Controllers\Contact\Persons;

use Illuminate\Support\Facades\DB;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Resources\ActivityResource;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Email\Repositories\EmailRepository;
use Webkul\Admin\Http\Controllers\Concerns\ConcatsEmailActivities;

class ActivityController extends Controller
{
    use ConcatsEmailActivities;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected ActivityRepository $activityRepository,
        protected EmailRepository $emailRepository,
        protected AttachmentRepository $attachmentRepository
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index($id)
    {
        $activities = $this->activityRepository
            ->leftJoin('person_activities', 'activities.id', '=', 'person_activities.activity_id')
            ->where('person_activities.person_id', $id)
            ->get();

        return ActivityResource::collection(
            $this->concatEmailActivitiesFor('person', (int) $id, $activities, $this->attachmentRepository)
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function concatEmailAsActivities($personId, $activities)
    {
        return $this->concatEmailActivitiesFor('person', (int) $personId, $activities, $this->attachmentRepository);
    }
}
