<?php

namespace Webkul\Admin\Http\Controllers\Contact\Persons;

use App\Enums\ActivityType;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
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
     * Start a patient message chat for a person.
     */
    public function store(int $id): RedirectResponse
    {
        if (! bouncer()->hasPermission('activities.create')) {
            abort(403);
        }

        $person = Person::query()->findOrFail($id);
        $existingActivity = $person->activities()
            ->where('type', ActivityType::PATIENT_MESSAGE->value)
            ->orderByDesc('updated_at')
            ->first();

        if ($existingActivity) {
            return redirect()->to(route('admin.contacts.persons.view', $person->id) . '#patient-berichten');
        }

        $activity = $this->activityRepository->create([
            'type'          => ActivityType::PATIENT_MESSAGE->value,
            'title'         => 'Berichten patiënt portaal',
            'comment'       => null,
            'user_id'       => auth()->guard('user')->id(),
            'schedule_from' => now(),
            'schedule_to'   => now(),
            'is_done'       => 0,
            'additional'    => [
                'skip_patient_message_creation' => true,
            ],
        ]);

        $activity->persons()->syncWithoutDetaching([$person->id]);

        return redirect()->to(route('admin.contacts.persons.view', $person->id) . '#patient-berichten');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function concatEmailAsActivities($personId, $activities)
    {
        return $this->concatEmailActivitiesFor('person', (int) $personId, $activities, $this->attachmentRepository);
    }
}
