<?php

namespace Webkul\Admin\Http\Controllers\Activity;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\EntityType;
use App\Enums\WebhookType;
use App\Models\CallStatus;
use App\Models\Department;
use App\Models\Order;
use App\Models\SalesLead;
use App\Services\patientmessages\PatientMessageService;
use App\Services\WebhookService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Activity\Repositories\FileRepository;
use Webkul\Activity\Services\ViewService;
use Webkul\Admin\DataGrids\Activity\ActivityDataGrid;
use App\Http\Controllers\Concerns\HandlesReturnUrl;
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
    use HandlesReturnUrl;
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
     * Return persons linked to a given entity (lead, sales_lead, or order).
     * Used by the file-upload dialog to let users pick which person to share with.
     */
    public function personsForEntity(Request $request): JsonResponse
    {
        $entityType = $request->query('entity_type');
        $entityId   = (int) $request->query('entity_id');

        $nameSelect = array_map(fn ($f) => "persons.{$f}", array_merge(['id'], Person::NAME_FIELDS));

        $persons = match ($entityType) {
            'lead'       => Lead::findOrFail($entityId)->persons()->select($nameSelect)->get(),
            'sales_lead' => SalesLead::findOrFail($entityId)->persons()->select($nameSelect)->get(),
            'order'      => optional(Order::findOrFail($entityId)->salesLead)?->persons()->select($nameSelect)->get() ?? collect(),
            default      => collect(),
        };

        return response()->json([
            'data' => $persons->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
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

        Event::dispatch('activity.create.before');

        // Auto-assign group if not specified but user has a group
        $data = request()->all();
        logger()->info('Activity store data', $data);

        // If lead_id is set, ensure we do not also bind a person via pivot
        if (!empty($data['lead_id'])) {
            unset($data['person_id']);
        }

        // Extract person_ids before passing data to repository (not a column on activities)
        $personIds = array_filter(array_map('intval', (array) ($data['person_ids'] ?? [])));
        unset($data['person_ids']);

        // Convert empty strings to null for foreign key constraints
        foreach (['lead_id', 'group_id', 'user_id'] as $field) {
            if (isset($data[$field]) && ($data[$field] === '' || $data[$field] === null)) {
                $data[$field] = null;
            }
        }

        // VeeValidate sends boolean false as the string "false" via FormData,
        // which PHP casts to true. Normalize using filter_var to get a proper boolean.
        $data['publish_to_portal'] = filter_var($data['publish_to_portal'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Ensure group_id is set and valid for lead activities
        $result = $this->ensureGroupIdForLeadActivity($data);
        if ($result !== null) {
            return $result; // Return error response if group_id could not be determined
        }

        $activity = $this->activityRepository->create(array_merge($data, [
            'is_done' => (request('type') == ActivityType::NOTE->value || request('type') == ActivityType::FILE->value ) ? 1 : 0,
        ]));

        // Link selected persons: use person_id FK for the primary person when no other entity is set
        if (!empty($personIds)) {
            $hasPrimaryEntity = !empty($data['lead_id']) || !empty($data['sales_lead_id'])
                || !empty($data['order_id']) || !empty($data['clinic_id']);

            if (!$hasPrimaryEntity && !$activity->person_id) {
                // Set the first person as the primary FK, link the rest via pivot
                $primaryPersonId = array_shift($personIds);
                $activity->update(['person_id' => $primaryPersonId]);
            }

            // Extra person_ids beyond the primary FK are no longer stored (pivot removed)
        }

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

        // Determine which entity this activity belongs to — driven by EntityType enum
        $relatedEntityType = EntityType::resolveFromActivity($activity);
        $relatedEntity     = $relatedEntityType
            ? $activity->{$relatedEntityType->getRelation()}
            : null;

        if ($relatedEntityType && ! $relatedEntity) {
            Log::error('Activity entity relation resolved to null', [
                'activity_id'  => $activity->id,
                'entity_type'  => $relatedEntityType->value,
                'foreign_key'  => $relatedEntityType->getForeignKey(),
                'foreign_value' => $activity->{$relatedEntityType->getForeignKey()},
            ]);
        }
        $user = auth()->guard('user')->user();
        $canTakeover = $user->hasPermission('activities.takeover');

        $callStatuses = CallStatus::where('activity_id', $activity->id)
            ->with('creator')
            ->orderByDesc('created_at')
            ->get();

        return view('admin::activities.edit', compact('activity', 'groups', 'lookUpEntityData', 'relatedEntity', 'relatedEntityType', 'canTakeover', 'callStatuses'));
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

        // VeeValidate sends boolean false as the string "false" via FormData,
        // which PHP casts to true. Normalize using filter_var to get a proper boolean.
        if (array_key_exists('publish_to_portal', $data)) {
            $data['publish_to_portal'] = filter_var($data['publish_to_portal'], FILTER_VALIDATE_BOOLEAN);
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

        // If a valid return_url is provided, prefer redirecting back to it
        $returnUrl = $this->resolveReturnUrl();
        if ($returnUrl) {
            return redirect($returnUrl);
        }

        // Fallback: when completing an activity without a return_url, redirect to the related entity
        $isCompleting = (isset($data['is_done']) && $data['is_done'])
            || ($requestedStatus !== null && $requestedStatus === ActivityStatus::DONE->value);

        if ($isCompleting) {
            if ($activity->order_id) {
                return redirect()->route('admin.orders.view', $activity->order_id);
            }

            if ($activity->lead_id) {
                return redirect()->route('admin.leads.view', $activity->lead_id);
            }

            if ($activity->person_id) {
                return redirect()->route('admin.contacts.persons.view', $activity->person_id);
            }
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

            $disk = $file->resolveDisk();

            if ($disk === null) {
                logger()->warning('Activity file download failed: file missing on all disks', [
                    'activity_file_id' => $file->id,
                    'path'             => $file->path,
                ]);
                abort(404);
            }

            $downloadName = basename($file->path);
            return Storage::disk($disk)->download($file->path, $downloadName);
        } catch (Exception $exception) {
            Log::warning('Activity file download failed: '.$exception->getMessage(), [
                'activity_file_id' => $id,
            ]);
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
        $firstPersonId = $activity->person_id;
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
            session()->flash('success', 'Activiteit is verwijderd.');

            $returnUrl = $this->resolveReturnUrl();
            if ($returnUrl) {
                return redirect($returnUrl);
            }

            if (!is_null($leadId)) {
                return redirect()->route('admin.leads.view', $leadId);
            } else {
                return redirect()->route('admin.contacts.persons.view', $firstPersonId);
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
