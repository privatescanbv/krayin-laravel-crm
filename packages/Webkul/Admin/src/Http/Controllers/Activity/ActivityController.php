<?php

namespace Webkul\Admin\Http\Controllers\Activity;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\WebhookType;
use App\Models\CallStatus;
use App\Models\Department;
use App\Services\patientmessages\PatientMessageService;
use App\Services\WebhookService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Activity\Repositories\FileRepository;
use Webkul\Activity\Services\ViewService;
use Webkul\Admin\DataGrids\Activity\ActivityDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Requests\MassUpdateRequest;
use Webkul\Admin\Http\Resources\ActivityResource;
use App\Services\ActivityStatusService;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\User\Models\Group;
use Webkul\User\Repositories\GroupRepository;

class ActivityController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected ActivityRepository $activityRepository,
        protected FileRepository $fileRepository,
        protected AttributeRepository $attributeRepository,
        protected WebhookService $webhookService,
        protected ?ViewService $viewService = null,
        private readonly PatientMessageService $patientMessageService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $views = [];
        $currentView = 'for_me';

        if ($this->viewService) {
            $views = $this->viewService->getAvailableViews();
            $currentView = request()->get('view', $this->viewService->getDefaultView()['key']);
        }

        return view('admin::activities.index', compact('views', 'currentView'));
    }

    /**
     * Returns a listing of the resource.
     */
    public function get(): JsonResponse
    {
        // Calendar view was removed; always return datagrid payload.
        // Intentionally ignore any `view_type` query param (e.g. `calendar`).
        return datagrid(ActivityDataGrid::class)->process();
    }

    /**
     * Return open (is_done = 0) activities for a given lead.
     */
    public function openByLead(int $leadId): JsonResponse
    {
        $activities = $this->activityRepository
            ->where('lead_id', $leadId)
            ->where('is_done', 0)
            ->orderBy('schedule_from', 'asc')
            ->get();

        return response()->json([
            'data' => ActivityResource::collection($activities),
        ]);
    }

    /**
     * Get available views.
     */
    public function getViews(): JsonResponse
    {
        $views = $this->viewService->getAvailableViews();

        return response()->json([
            'views' => $views,
        ]);
    }

    /**
     * Display the specified activity in view mode.
     */
    public function view(int $id): View
    {
        $activity = $this->activityRepository->with(['lead', 'salesLead', 'clinic', 'files'])->findOrFail($id);

        $callStatuses = CallStatus::where('activity_id', $activity->id)
            ->with('creator')
            ->orderByDesc('created_at')
            ->get();

        if(ActivityType::PATIENT_MESSAGE->value == $activity->type?->value) {
            $this->patientMessageService->markAllMessagesAsReadForEmployee($activity);
        }

        return view('admin::activities.view', compact('activity', 'callStatuses'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(): RedirectResponse|JsonResponse
    {
        $this->validate(request(), [
            'type'          => 'required',
            'comment'       => 'required_if:type,note',
            'schedule_from' => 'required_unless:type,note,file',
            'schedule_to'   => 'required_unless:type,note,file',
            'file'          => 'required_if:type,file',
        ]);

        if (request('type') === 'meeting') {
            /**
             * Check if meeting is overlapping with other meetings.
             */
            $isOverlapping = $this->activityRepository->isDurationOverlapping(
                request()->input('schedule_from'),
                request()->input('schedule_to'),
                null, // No participants check needed
                request()->input('id')
            );

            if ($isOverlapping) {
                if (request()->ajax()) {
                    return response()->json([
                        'message' => trans('admin::app.activities.overlapping-error'),
                    ], 400);
                }

                session()->flash('success', trans('admin::app.activities.overlapping-error'));

                return redirect()->back();
            }
        }

        Event::dispatch('activity.create.before');

        // Auto-assign group if not specified but user has a group
        $data = request()->all();
        logger()->info('Activity store data', $data);

        // If lead_id is set, ensure we do not also bind a person via pivot
        if (!empty($data['lead_id'])) {
            unset($data['person_id']);
        }

        // Convert empty strings to null for foreign key constraints
        foreach (['lead_id', 'group_id', 'user_id'] as $field) {
            if (isset($data[$field]) && ($data[$field] === '' || $data[$field] === null)) {
                $data[$field] = null;
            }
        }

        // Ensure group_id is set and valid for lead activities
        $result = $this->ensureGroupIdForLeadActivity($data);
        if ($result !== null) {
            return $result; // Return error response if group_id could not be determined
        }

        $activity = $this->activityRepository->create(array_merge($data, [
            'is_done' => request('type') == 'note' ? 1 : 0,
        ]));

        $didChange = $this->updateStatus($activity);
        if ($didChange) {
            $activity->save();
        }

        Event::dispatch('activity.create.after', $activity);

        if (request()->ajax()) {
            return response()->json([
                'data'    => new ActivityResource($activity),
                'message' => trans('admin::app.activities.create-success'),
            ]);
        }

        session()->flash('success', trans('admin::app.activities.create-success'));

        return redirect()->back();
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View
    {
        $activity = $this->activityRepository->findOrFail($id);

        $groups = app(GroupRepository::class)->all();

        $leadId = old('lead_id') ?? $activity->lead_id;

        $lookUpEntityData = $this->attributeRepository->getLookUpEntity('leads', $leadId);

        // Determine which entity this activity belongs to
        $relatedEntity = null;
        $relatedEntityName = null;

        if ($activity->lead_id) {
            $relatedEntity = $activity->lead;
            $relatedEntityName = 'Lead';
        }elseif ($activity->sales_lead_id) {
            $relatedEntity = $activity->salesLead;
            $relatedEntityName = 'Sales';
        } elseif ($activity->persons()->count() > 0) {
            $relatedEntity = $activity->persons()->first();
            $relatedEntityName = 'Person';
        }
        $user = auth()->guard('user')->user();
        $canTakeover = $user->hasPermission('activities.takeover');

        $callStatuses = CallStatus::where('activity_id', $activity->id)
            ->with('creator')
            ->orderByDesc('created_at')
            ->get();

        return view('admin::activities.edit', compact('activity', 'groups', 'lookUpEntityData', 'relatedEntity', 'relatedEntityName', 'canTakeover', 'callStatuses'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update($id): RedirectResponse|JsonResponse
    {
        // Get the current activity to check permissions
        $activity = $this->activityRepository->findOrFail($id);

        // Check if user_id is being changed and if user has permission
        if (request()->has('user_id') && request('user_id') != $activity->user_id) {
            $currentUser = auth()->guard('user')->user();

            // Only allow user_id change if:
            // 1. Current user is the assigned user, OR
            // 2. Current user has takeover permission
            if ($activity->user_id && $activity->user_id != $currentUser->id && !$currentUser->hasPermission('activities.takeover')) {
                if (request()->ajax()) {
                    return response()->json([
                        'message' => 'Je hebt geen rechten om de toewijzing van deze activiteit te wijzigen.',
                    ], 403);
                }

                session()->flash('error', 'Je hebt geen rechten om de toewijzing van deze activiteit te wijzigen.');
                return redirect()->back();
            }
        }

        // Validate that assigned user is active
        if (request()->has('user_id') && request('user_id')) {
            $validator = Validator::make(
                ['user_id' => request('user_id')],
                ['user_id' => 'active_user']
            );

            if ($validator->fails()) {
                if (request()->ajax()) {
                    return response()->json([
                        'message' => $validator->errors()->first('user_id'),
                    ], 422);
                }

                session()->flash('error', $validator->errors()->first('user_id'));
                return redirect()->back();
            }
        }

        Event::dispatch('activity.update.before', $id);

        $data = request()->all();

        // Convert empty strings to null for foreign key constraints
        foreach (['lead_id', 'group_id', 'user_id'] as $field) {
            if (isset($data[$field]) && ($data[$field] === '')) {
                $data[$field] = null;
            }
        }

        $requestedStatus = isset($data['status']) ? (string) $data['status'] : null;

        $activity = $this->activityRepository->update($data, $id);

        // Synchronize is_done and status both ways
        $didChange = false;

        // If is_done explicitly provided, take precedence
        if (array_key_exists('is_done', $data)) {
            $didChange = $this->updateStatus($activity);
        } elseif ($requestedStatus !== null) {
            // TODO refactor this code, simplify
            // If status explicitly provided
            if ($requestedStatus === ActivityStatus::DONE->value) {
                if (!$activity->is_done) {
                    $activity->is_done = 1;
                    $didChange = true;
                }
                if (($activity->status?->value ?? null) !== ActivityStatus::DONE->value) {
                    $activity->status = ActivityStatus::DONE;
                    $didChange = true;
                }
            } elseif ($requestedStatus === ActivityStatus::IN_PROGRESS->value) {
                // In progress is always allowed
                if ($activity->is_done) {
                    $activity->is_done = 0;
                    $didChange = true;
                }
                if (($activity->status?->value ?? null) !== ActivityStatus::IN_PROGRESS->value) {
                    $activity->status = ActivityStatus::IN_PROGRESS;
                    $didChange = true;
                }
            } else {
                // Other statuses must match computed
                if ($activity->is_done) {
                    $activity->is_done = 0;
                    $didChange = true;
                }
                $computed = ActivityStatusService::computeStatus($activity->schedule_from, $activity->schedule_to, $activity->status);
                if ($computed->value !== $requestedStatus) {
                    // Reject with computed suggestion
                    if (($activity->status?->value ?? null) !== $computed->value) {
                        $activity->status = $computed;
                        $didChange = true;
                    }
                    if (request()->ajax()) {
                        $labels = [
                            ActivityStatus::ACTIVE->value => 'Actief',
                            ActivityStatus::IN_PROGRESS->value => 'In behandeling',
                            ActivityStatus::ON_HOLD->value => 'On hold',
                            ActivityStatus::EXPIRED->value => 'Verlopen',
                            ActivityStatus::DONE->value => 'Afgerond',
                        ];

                        return response()->json([
                            'message' => 'Deze status is niet mogelijk voor de huidige datumrange.',
                            'status'  => $computed->value,
                            'status_label' => $labels[$computed->value] ?? $computed->value,
                        ], 422);
                    }
                } else {
                    if (($activity->status?->value ?? null) !== $computed->value) {
                        $activity->status = $computed;
                        $didChange = true;
                    }
                }
            }
        } else {
            // No explicit flags, keep consistent automatically (respect IN_PROGRESS sticky and DONE sticky via service)
            $computed = ActivityStatusService::computeStatus($activity->schedule_from, $activity->schedule_to, $activity->status);
            if (($activity->status?->value ?? null) !== $computed->value) {
                $activity->status = $computed;
                $didChange = true;
            }
        }

        if ($didChange) {
            $activity->save();
        }

        // Send webhook if activity is marked as done
        if (isset($data['is_done']) && $data['is_done']) {
            $this->webhookService->sendWebhook([
                'type' => WebhookType::LEAD_ACTIVITY_IS_DONE->value,
                'activity' => [
                    'id' => $activity->id,
                    'type' => $activity->type,
                    'title' => $activity->title,
                    'comment' => $activity->comment,
                    'schedule_from' => $activity->schedule_from,
                    'schedule_to' => $activity->schedule_to,
                    'lead_id' => $activity->lead_id,
                ],
            ],
                WebhookType::LEAD_ACTIVITY_IS_DONE);
        }

        Event::dispatch('activity.update.after', $activity);

        if (request()->ajax()) {
            return response()->json([
                'data'    => new ActivityResource($activity),
                'message' => trans('admin::app.activities.update-success'),
                'status'  => $activity->status?->value,
            ]);
        }

        session()->flash('success', trans('admin::app.activities.update-success'));

        // If a safe return URL is provided, prefer redirecting back to it
        $returnUrl = request()->get('return');
        if (is_string($returnUrl) && str_starts_with($returnUrl, '/')) {
            return redirect($returnUrl);
        }

        // After completing, redirect to related lead view if available
        $shouldRedirectToLead = false;
        if (isset($data['is_done']) && $data['is_done']) {
            $shouldRedirectToLead = true;
        } elseif ($requestedStatus !== null && $requestedStatus === ActivityStatus::DONE->value) {
            $shouldRedirectToLead = true;
        }

        if ($shouldRedirectToLead && $activity->lead_id) {
            return redirect()->route('admin.leads.view', $activity->lead_id);
        }

        return redirect()->route('admin.activities.index');
    }

    /**
     * Mass Update the specified resources.
     */
    public function massUpdate(MassUpdateRequest $massUpdateRequest): JsonResponse
    {
        $activities = $this->activityRepository->findWhereIn('id', $massUpdateRequest->input('indices'));

        foreach ($activities as $activity) {
            Event::dispatch('activity.update.before', $activity->id);

            $activity = $this->activityRepository->update([
                'is_done' => $massUpdateRequest->input('value'),
            ], $activity->id);

            // Send webhook if activity is marked as done
            if ($massUpdateRequest->input('value')) {
                $this->webhookService->sendWebhook([
                    'type' => WebhookType::LEAD_ACTIVITY_IS_DONE->value,
                    'activity' => [
                        'id' => $activity->id,
                        'type' => $activity->type,
                        'title' => $activity->title,
                        'comment' => $activity->comment,
                        'schedule_from' => $activity->schedule_from,
                        'schedule_to' => $activity->schedule_to,
                        'lead_id' => $activity->lead_id,
                    ],
                ],
                    WebhookType::LEAD_ACTIVITY_IS_DONE);
            }

            Event::dispatch('activity.update.after', $activity);
        }

        return response()->json([
            'message' => trans('admin::app.activities.mass-update-success'),
        ]);
    }

    /**
     * Download file from storage.
     */
    public function download(int $id): StreamedResponse
    {
        try {
            $file = $this->fileRepository->findOrFail($id);

            $candidateDisks = array_values(array_unique(array_filter([
                config('filesystems.default'),
                'public',
                'local',
            ])));

            foreach ($candidateDisks as $disk) {
                try {
                    if (Storage::disk($disk)->exists($file->path)) {
                        $downloadName = $file->name ?: basename($file->path);

                        return Storage::disk($disk)->download($file->path, $downloadName);
                    }
                } catch (Exception $e) {
                    // Ignore disk-specific errors and try the next disk.
                    continue;
                }
            }

            logger()->warning('Activity file download failed: file missing on disks', [
                'activity_file_id' => $file->id,
                'path'             => $file->path,
                'disks_tried'       => $candidateDisks,
            ]);

            abort(404);
        } catch (Exception $exception) {
            abort(404);
        }
    }

    /*
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): RedirectResponse|JsonResponse
    {
        $activity = $this->activityRepository->findOrFail($id);
        $leadId = $activity->lead_id;
        $firstPersonId = $activity->persons()->first()?->id;
        try {
            Event::dispatch('activity.delete.before', $id);
            $activity->delete();

            Event::dispatch('activity.delete.after', $id);

            // ✅ JSON request (AJAX / API)
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Activiteit is verwijderd.',
                    'data'    => [
                        'activity_id' => $id,
                    ],
                ]);
            }
            if(!is_null($leadId)) {
                return redirect()->route('admin.leads.view', $leadId)->with('Activiteit is verwijderd.');
            } else {
                return redirect()->route('admin.contacts.persons.view', $firstPersonId)->with('Activiteit is verwijderd.');
            }
        } catch (Exception $exception) {
            logger()->error('Could not delete activity: '.$exception->getMessage());
            return response()->json([
                'message' => trans('admin::app.activities.destroy-failed'),
            ], 400);
        }
    }

    /**
     * Mass Delete the specified resources.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $activities = $this->activityRepository->findWhereIn('id', $massDestroyRequest->input('indices'));

        try {
            foreach ($activities as $activity) {
                Event::dispatch('activity.delete.before', $activity->id);

                $this->activityRepository->delete($activity->id);

                Event::dispatch('activity.delete.after', $activity->id);
            }

            return response()->json([
                'message' => trans('admin::app.activities.mass-destroy-success'),
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.activities.mass-delete-failed'),
            ], 400);
        }
    }

    /**
     * Ensure group_id is set for lead activities by auto-determining from lead's department.
     *
     * @param array $data Activity data (passed by reference)
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|null
     */
    private function ensureGroupIdForLeadActivity(array &$data)
    {
        // If lead_id is provided, group_id is required
        if (!empty($data['lead_id'])) {
            $lead = app(LeadRepository::class)->findOrFail($data['lead_id']);
            if (!isset($data['group_id']) || !$data['group_id']) {
                try {
                    $data['group_id'] = Department::getGroupIdForLead($lead);
                } catch (Exception $e) {
                    if (request()->ajax()) {
                        return response()->json([
                            'message' => 'Kan geen groep bepalen voor deze activiteit. Lead heeft geen geldig department.',
                        ], 422);
                    }
                    session()->flash('error', 'Kan geen groep bepalen voor deze activiteit. Lead heeft geen geldig department.');
                    return redirect()->back();
                }
            } else {
                // Validate provided group_id belongs to the same department as the lead
                $group = Group::query()->find($data['group_id']);
                if (!$group || ($group->department_id !== $lead->department_id)) {
                    if (request()->ajax()) {
                        return response()->json([
                            'message' => 'De opgegeven groep komt niet overeen met het departement van de lead.',
                        ], 422);
                    }
                    session()->flash('error', 'De opgegeven groep komt niet overeen met het departement van de lead.');
                    return redirect()->back();
                }
            }
        }

        return null; // Success - no error response needed
    }

    /**
     * @param Activity $activity
     * @return bool true, if entity has changed and needs saving
     */
    private function updateStatus(Activity $activity) :bool
    {
        if ($activity->is_done) {
            if (($activity->status?->value ?? null) !== ActivityStatus::DONE->value) {
                $activity->status = ActivityStatus::DONE;
                return true;
            }
        } else {
            // Un-done: compute status based on dates
            $computed = ActivityStatusService::computeStatus($activity->schedule_from, $activity->schedule_to, ActivityStatus::ACTIVE);
            if (($activity->status?->value ?? null) !== $computed->value) {
                $activity->status = $computed;
                return true;
            }
        }
        return false;

    }

}
