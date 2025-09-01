<?php

namespace Webkul\Admin\Http\Controllers\Activity;

use App\Enums\WebhookType;
use App\Services\WebhookService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Activity\Repositories\FileRepository;
use Webkul\Activity\Services\ViewService;
use Webkul\Admin\DataGrids\Activity\ActivityDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Requests\MassUpdateRequest;
use Webkul\Admin\Http\Resources\ActivityResource;
use Webkul\Attribute\Repositories\AttributeRepository;
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
        if (! request()->has('view_type')) {
            return datagrid(ActivityDataGrid::class)->process();
        }

        $startDate = request()->get('startDate')
            ? Carbon::createFromTimeString(request()->get('startDate').' 00:00:01')
            : Carbon::now()->startOfWeek()->format('Y-m-d H:i:s');

        $endDate = request()->get('endDate')
            ? Carbon::createFromTimeString(request()->get('endDate').' 23:59:59')
            : Carbon::now()->endOfWeek()->format('Y-m-d H:i:s');

        $view = request()->get('view');
        $activities = $this->activityRepository->getActivities([$startDate, $endDate], $view)->toArray();

        return response()->json([
            'activities' => $activities,
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
        
        // Convert empty strings to null for foreign key constraints
        foreach (['lead_id', 'group_id', 'user_id'] as $field) {
            if (isset($data[$field]) && ($data[$field] === '' || $data[$field] === null)) {
                $data[$field] = null;
            }
        }
        
        if (!isset($data['group_id']) || !$data['group_id']) {
            // First try to get group from lead's department if lead_id is provided
            if (!empty($data['lead_id'])) {
                $lead = app(\Webkul\Lead\Repositories\LeadRepository::class)->find($data['lead_id']);
                if ($lead) {
                    $data['group_id'] = $lead->getDefaultGroupId();
                }
            }
            
            // Fallback: if no group from lead, try user's group
            if (!isset($data['group_id']) || !$data['group_id']) {
                $userId = $data['user_id'] ?? auth()->guard('user')->id();
                if ($userId) {
                    $user = \Webkul\User\Models\User::find($userId);
                    if ($user && $user->groups()->count() > 0) {
                        $data['group_id'] = $user->groups()->first()->id;
                    }
                }
            }
        }

        // Ensure we have a group_id before creating the activity
        if (!isset($data['group_id']) || !$data['group_id']) {
            if (request()->ajax()) {
                return response()->json([
                    'message' => 'Kan geen standaard groep bepalen voor deze activiteit. Selecteer handmatig een groep.',
                ], 422);
            }

            session()->flash('error', 'Kan geen standaard groep bepalen voor deze activiteit. Selecteer handmatig een groep.');
            return redirect()->back();
        }

        $activity = $this->activityRepository->create(array_merge($data, [
            'is_done' => request('type') == 'note' ? 1 : 0,
        ]));

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
        } elseif ($activity->persons()->count() > 0) {
            $relatedEntity = $activity->persons()->first();
            $relatedEntityName = 'Person';
        } elseif ($activity->products()->count() > 0) {
            $relatedEntity = $activity->products()->first();
            $relatedEntityName = 'Product';
        } elseif ($activity->warehouses()->count() > 0) {
            $relatedEntity = $activity->warehouses()->first();
            $relatedEntityName = 'Warehouse';
        }

        $user = auth()->guard('user')->user();
        $canTakeover = $user->hasPermission('activities.takeover');

        return view('admin::activities.edit', compact('activity', 'groups', 'lookUpEntityData', 'relatedEntity', 'relatedEntityName', 'canTakeover'));
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

        Event::dispatch('activity.update.before', $id);

        $data = request()->all();
        
        // Convert empty strings to null for foreign key constraints
        foreach (['lead_id', 'group_id', 'user_id'] as $field) {
            if (isset($data[$field]) && ($data[$field] === '' || $data[$field] === null)) {
                $data[$field] = null;
            }
        }
        
        $activity = $this->activityRepository->update($data, $id);

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
            ]);
        }

        session()->flash('success', trans('admin::app.activities.update-success'));

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

            return Storage::download($file->path);
        } catch (Exception $exception) {
            abort(404);
        }
    }

    /*
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $activity = $this->activityRepository->findOrFail($id);

        try {
            Event::dispatch('activity.delete.before', $id);

            $activity?->delete($id);

            Event::dispatch('activity.delete.after', $id);

            return response()->json([
                'message' => trans('admin::app.activities.destroy-success'),
            ], 200);
        } catch (\Exception $exception) {
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
        } catch (\Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.activities.mass-delete-failed'),
            ], 400);
        }
    }
}
