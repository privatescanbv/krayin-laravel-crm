<?php

namespace Webkul\Admin\Http\Controllers\Lead;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Resources\ActivityResource;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Email\Repositories\EmailRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\User\Models\User;

class ActivityController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected ActivityRepository $activityRepository,
        protected EmailRepository $emailRepository,
        protected AttachmentRepository $attachmentRepository,
        protected LeadRepository $leadRepository
    ) {}

    /**
     * Store a newly created activity in storage.
     */
    public function store(Request $request, int $id)
    {
        $this->validate($request, [
            'type'          => 'required',
            'comment'       => 'required_if:type,note',
            'description'   => 'nullable|string',
            'schedule_from' => 'required_unless:type,note,file|date_format:Y-m-d H:i:s',
            'schedule_to'   => 'required_unless:type,note,file|date_format:Y-m-d H:i:s',
            'file'          => 'required_if:type,file',
        ]);

        $lead = $this->leadRepository->findOrFail($id);

        // Get the first user as fallback for API requests
        $user = auth()->guard('user')->user() ?? User::query()->first();

        if (! $user) {
            return response()->json([
                'message' => 'No user found',
            ], 500);
        }

        $activity = $this->activityRepository->create(array_merge($request->all(), [
            'is_done' => $request->type == 'note' ? 1 : 0,
            'user_id' => $user->id,
        ]));

        $lead->activities()->attach($activity->id);

        return response()->json([
            'data'    => new ActivityResource($activity),
            'message' => trans('admin::app.activities.create-success'),
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {
        $activities = $this->activityRepository
            ->leftJoin('lead_activities', 'activities.id', '=', 'lead_activities.activity_id')
            ->where('lead_activities.lead_id', $id)
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

        // Get the first user as fallback for API requests
        $user = auth()->guard('user')->user() ?? User::query()->first();

        return $activities->concat($emails->map(function ($email) use ($user) {
            return (object) [
                'id'            => $email->id,
                'parent_id'     => $email->parent_id,
                'title'         => $email->subject,
                'type'          => 'email',
                'is_done'       => 1,
                'comment'       => $email->reply,
                'schedule_from' => null,
                'schedule_to'   => null,
                'user'          => $user,
                'participants'  => [],
                'location'      => null,
                'additional'    => [
                    'folders' => json_decode($email->folders),
                    'from'    => json_decode($email->from),
                    'to'      => json_decode($email->reply_to),
                    'cc'      => json_decode($email->cc),
                    'bcc'     => json_decode($email->bcc),
                ],
                'files'         => $this->attachmentRepository->findWhere(['email_id' => $email->id])->map(function ($attachment) {
                    return (object) [
                        'id'         => $attachment->id,
                        'name'       => $attachment->name,
                        'path'       => $attachment->path,
                        'url'        => $attachment->url,
                        'created_at' => $attachment->created_at,
                        'updated_at' => $attachment->updated_at,
                    ];
                }),
                'created_at'    => $email->created_at,
                'updated_at'    => $email->updated_at,
            ];
        }))->sortByDesc('id')->sortByDesc('created_at');
    }
}
