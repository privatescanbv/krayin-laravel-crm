<?php

namespace App\Http\Controllers\Admin\Settings\Clinic;

use App\Http\Controllers\Concerns\HandlesReturnUrl;
use App\Repositories\ClinicRepository;
use Illuminate\Http\Request;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Admin\Http\Controllers\Concerns\ConcatsEmailActivities;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Resources\ActivityResource;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Email\Repositories\EmailRepository;

class ActivityController extends Controller
{
    use ConcatsEmailActivities, HandlesReturnUrl;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected ActivityRepository $activityRepository,
        protected EmailRepository $emailRepository,
        protected AttachmentRepository $attachmentRepository,
        protected ClinicRepository $clinicRepository
    ) {}

    /**
     * Store a newly created activity in storage.
     */
    public function store(Request $request, int $id)
    {
        request()->validate($request, [
            'type'          => 'required|in:task,meeting,call',
            'comment'       => 'required_if:type,note',
            'description'   => 'nullable|string',
            'user_id'       => 'nullable|exists:users,id',
            'schedule_from' => 'required_unless:type,note,file|date_format:Y-m-d H:i:s',
            'schedule_to'   => 'required_unless:type,note,file|date_format:Y-m-d H:i:s',
            'file'          => 'required_if:type,file',
        ]);
        $request['comment'] = $request->description;

        // Ensure lead_id and person_id are not saved
        $request->request->remove('lead_id');
        $request->request->remove('person_id');

        // Convert empty strings to null for foreign key constraints
        $data = $request->all();
        foreach (['user_id', 'group_id'] as $field) {
            if (isset($data[$field]) && ($data[$field] === '' || $data[$field] === null)) {
                $data[$field] = null;
            }
        }

        // Check clinic exists
        $clinic = $this->clinicRepository->findOrFail($id);

        // Duplicate guard: same title on same clinic with is_done = 0 should be rejected
        $isDuplicate = $this->activityRepository
            ->where('clinic_id', $id)
            ->where('title', $data['title'] ?? null)
            ->where('is_done', 0)
            ->exists();

        if ($isDuplicate) {
            return response()->json([
                'message' => __('messages.activity.duplicate_clinic'),
                'errors'  => ['title' => [__('messages.activity.duplicate_clinic')]],
            ], 409);
        }

        $activity = $this->activityRepository->create(array_merge($data, [
            'is_done'   => $request->type === 'note' ? 1 : 0,
            'user_id'   => $data['user_id'] ?? null,
            'group_id'  => $data['group_id'] ?? null,
            'clinic_id' => $id,
        ]));

        return response()->json([
            'data'    => new ActivityResource($activity),
            'message' => trans('admin::app.activities.create-success'),
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index($id)
    {
        $activities = $this->activityRepository
            ->where('clinic_id', $id)
            ->get();

        return ActivityResource::collection(
            $this->concatEmailActivitiesFor('clinic', (int) $id, $activities, $this->attachmentRepository)
        );
    }

    /**
     * Concat emails as activities
     */
    public function concatEmailAsActivities($clinicId, $activities)
    {
        return $this->concatEmailActivitiesFor('clinic', (int) $clinicId, $activities, $this->attachmentRepository);
    }
}
