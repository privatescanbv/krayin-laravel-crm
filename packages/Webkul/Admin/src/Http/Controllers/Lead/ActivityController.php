<?php

namespace Webkul\Admin\Http\Controllers\Lead;

use App\Models\Department;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Resources\ActivityResource;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Email\Repositories\EmailRepository;
use Webkul\Lead\Repositories\LeadRepository;
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
        protected ActivityRepository   $activityRepository,
        protected EmailRepository      $emailRepository,
        protected AttachmentRepository $attachmentRepository,
        protected LeadRepository       $leadRepository
    )
    {
    }

    /**
     * Get the default group for a lead based on its department.
     */
    public function getDefaultGroup(int $id)
    {
        $lead = $this->leadRepository->findOrFail($id);
        $groupId = Department::getGroupIdForLead($lead);

        return response()->json([
            'group_id' => $groupId,
        ]);
    }

    /**
     * Store a newly created activity in storage.
     */
    public function store(Request $request, int $id)
    {
        try {
            $this->validate($request, [
                'type' => 'required|in:task,meeting,call',
                'comment' => 'required_if:type,note',
                'description' => 'nullable|string',
                'user_id' => 'nullable|exists:users,id',
                'schedule_from' => 'required_unless:type,note,file|date_format:Y-m-d H:i:s',
                'schedule_to' => 'required_unless:type,note,file|date_format:Y-m-d H:i:s',
                'file' => 'required_if:type,file',
            ]);
            $request['comment'] = $request->description;

            // Ensure person_id is not saved when storing activity for a lead
            $request->request->remove('person_id');

            // Convert empty strings to null for foreign key constraints
            $data = $request->all();
            foreach (['user_id'] as $field) {
                if (isset($data[$field]) && ($data[$field] === '' || $data[$field] === null)) {
                    $data[$field] = null;
                }
            }

            // Always load the lead for department validation
            $lead = $this->leadRepository->findOrFail($id);
            $groupId = Department::getGroupIdForLead($lead);

            // Duplicate guard: same title on same lead with is_done = 0 should be rejected
            $isDuplicate = $this->activityRepository
                ->where('lead_id', $id)
                ->where('title', $data['title'] ?? null)
                ->where('is_done', 0)
                ->exists();

            if ($isDuplicate) {
                Log::warning('Lead activities store: duplicate detected', [
                    'lead_id' => $id,
                    'title' => $data['title'] ?? null,
                ]);

                return response()->json([
                    'message' => 'Duplicate activity: same title exists for this lead and is not done.',
                    'errors' => [
                        'title' => ['Duplicate for this lead while open (is_done = 0).']
                    ]
                ], 409);
            }

            $activity = $this->activityRepository->create(array_merge($data, [
                'is_done' => $request->type == 'note' ? 1 : 0,
                'user_id' => $data['user_id'] ?? null,
                'group_id' => $groupId,
                'lead_id' => $id,
            ]));
            return response()->json([
                'data' => new ActivityResource($activity),
                'message' => trans('admin::app.activities.create-success'),
            ]);
        } catch (Exception $e) {
            Log::error('Lead activities store: exception', [
                'lead_id' => $id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index($id)
    {
            $activities = $this->activityRepository
                ->with('emails')
                ->where('lead_id', $id)
                ->get();

        return ActivityResource::collection($this->concatEmailAsActivities($id, $activities));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function concatEmailAsActivities($leadId, $activities)
    {
        $emails = DB::table('emails as child')
            ->select('child.*')
            ->join('emails as parent', 'child.parent_id', '=', 'parent.id')
            ->where('parent.lead_id', $leadId)
            ->union(DB::table('emails as parent')->where('parent.lead_id', $leadId))
            ->get();

        return $this->concatEmails($activities, $emails, $this->attachmentRepository);
    }
}
