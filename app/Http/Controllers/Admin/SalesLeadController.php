<?php

namespace App\Http\Controllers\Admin;

use App\DataGrids\SalesLeadDataGrid;
use App\Enums\ActivityStatus;
use App\Enums\LostReason;
use App\Enums\PipelineType;
use App\Helpers\RequestHelper;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SalesLead;
use App\Repositories\SalesLeadRepository;
use App\Services\PipelineCookieService;
use App\Services\StageTransitionAttributes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Enum;
use Prettus\Repository\Criteria\RequestCriteria;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Admin\Http\Controllers\Concerns\ConcatsEmailActivities;
use Webkul\Admin\Http\Controllers\Concerns\HasAdvancedSearch;
use Webkul\Admin\Http\Resources\ActivityResource;
use Webkul\Admin\Http\Resources\SalesLeadLookupResource;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\StageProxy;
use Webkul\Lead\Repositories\PipelineRepository;

class SalesLeadController extends Controller
{
    use ConcatsEmailActivities, HasAdvancedSearch;

    private bool $enableDebugLogging = false;

    public function __construct(
        private readonly SalesLeadRepository $salesLeadRepository,
        private readonly PipelineRepository $pipelineRepository,
        private readonly PipelineCookieService $pipelineCookieService,
        private readonly ActivityRepository $activityRepository,
        private readonly AttachmentRepository $attachmentRepository,
    ) {}

    public function index(Request $request)
    {
        // Get selected pipeline or default workflow pipeline
        $pipeline = $this->pipelineCookieService->getPipeline(PipelineType::BACKOFFICE, $request->pipeline_id);

        $stages = $pipeline->stages->map(function ($stage) {
            return [
                'id'               => $stage->id,
                'code'             => $stage->code,
                'name'             => $stage->name,
                'description'      => $stage->description,
                'sort_order'       => $stage->sort_order,
                'lead_pipeline_id' => $stage->lead_pipeline_id,
                'is_won'           => (bool) $stage->is_won,
                'is_lost'          => (bool) $stage->is_lost,
                'leads'            => [
                    'data' => [],
                    'meta' => [
                        'total'        => 0,
                        'current_page' => 1,
                        'per_page'     => 10,
                        'last_page'    => 1,
                    ],
                ],
            ];
        })->toArray();

        return view('adminc.sales_leads.index', [
            'pipeline' => $pipeline,
            'stages'   => $stages,
        ]);
    }

    public function get(Request $request)
    {
        if ($request->has('view_type') && $request->view_type === 'table') {
            $dataGrid = app(SalesLeadDataGrid::class);

            return $dataGrid->toJson();
        }

        $pipeline = $this->pipelineCookieService->getPipeline(PipelineType::BACKOFFICE, $request->pipeline_id);

        $stages = $pipeline->stages;
        $data = [];

        foreach ($stages as $stage) {
            // Optionally skip stages that are marked won/lost when requested
            if ($request->boolean('exclude_won_lost') && ($stage->is_won || $stage->is_lost)) {
                continue;
            }

            $query = SalesLead::with(['stage', 'lead', 'user', 'orders'])
                ->where('pipeline_stage_id', $stage->id);
            $salesLeads = $query->get();

            $salesLeads = $salesLeads->map(function ($salesLead) {
                $person = $salesLead->persons()->first();

                $stagePayload = $salesLead->stage ? [
                    'id'      => $salesLead->stage->id,
                    'name'    => $salesLead->stage->name,
                    'code'    => $salesLead->stage->code,
                    'is_won'  => (bool) $salesLead->stage->is_won,
                    'is_lost' => (bool) $salesLead->stage->is_lost,
                ] : null;

                return [
                    'id'                => $salesLead->id,
                    'name'              => $salesLead->name,
                    'description'       => $salesLead->description,
                    'pipeline_stage_id' => $salesLead->pipeline_stage_id,
                    // Keep legacy key, but also provide `stage` for parity with Lead payloads consumed by the kanban.
                    'pipeline_stage'    => $stagePayload,
                    'stage'             => $stagePayload,
                    'lead'              => $salesLead->lead ? [
                        'id'     => $salesLead->lead->id,
                        'title'  => $salesLead->lead->title,
                        'person' => $person ? [
                            'id'           => $person->id,
                            'name'         => $person->name,
                            'organization' => $person->organization ? [
                                'name' => $person->organization->name,
                            ] : null,
                        ] : null,
                    ] : null,
                    'user_id' => $salesLead->user_id,
                    'user'    => $salesLead->user ? [
                        'id'   => $salesLead->user->id,
                        'name' => $salesLead->user->name,
                    ] : null,
                    'created_at'            => $salesLead->created_at,
                    'open_activities_count' => $salesLead->open_activities_count,
                    'unread_emails_count'   => $salesLead->unread_emails_count ?? 0,
                    'has_duplicates'        => $salesLead->has_duplicates ?? false,
                    'duplicates_count'      => $salesLead->duplicates_count ?? 0,
                    'rotten_days'           => $salesLead->rotten_days ?? 0,
                    'days_until_due_date'   => $salesLead->days_until_due_date ?? null,
                    'mri_status'            => $salesLead->mri_status ?? null,
                    'mri_status_label'      => $salesLead->mri_status_label ?? null,
                    'has_diagnosis_form'    => $salesLead->has_diagnosis_form ?? false,
                    'lost_reason_label'     => $salesLead->lost_reason_label ?? null,
                    'has_multiple_persons'  => $salesLead->hasMultiplePersons(),
                    'persons_count'         => $salesLead->persons_count,
                    'orders'                => $salesLead->orders->map(function ($order) {
                        $status = $order->status;

                        return [
                            'id'           => $order->id,
                            'status'       => $status?->value,
                            'status_label' => $status?->label(),
                        ];
                    }),
                ];
            });

            $data[$stage->sort_order] = [
                'id'               => $stage->id,
                'code'             => $stage->code,
                'name'             => $stage->name,
                'description'      => $stage->description,
                'sort_order'       => $stage->sort_order,
                'lead_pipeline_id' => $stage->lead_pipeline_id,
                'is_won'           => (bool) $stage->is_won,
                'is_lost'          => (bool) $stage->is_lost,
                'leads'            => [
                    'data' => $salesLeads,
                    'meta' => [
                        'total'        => $salesLeads->count(),
                        'current_page' => 1,
                        'per_page'     => 10,
                        'last_page'    => 1,
                    ],
                ],
            ];
        }

        return response()->json($data);
    }

    public function store(Request $request)
    {
        $request->validate($this->getValidationRules(true));

        $salesLead = SalesLead::create($this->prepareSalesLeadData($request->all()));
        $lead = Lead::find($request->lead_id);
        $salesLead->copyFromLead($lead);

        // Handle person relationships
        $personIds = RequestHelper::filterIntegerArray($request, 'person_ids');
        if (! empty($personIds)) {
            // override the default persons from lead
            $salesLead->syncPersons($personIds);
        }

        return redirect()->route('admin.sales-leads.index')
            ->with('success', __('messages.sales.created'));
    }

    public function edit($id)
    {
        $salesLead = SalesLead::with(['contactPerson', 'lead.contactPerson', 'user'])->findOrFail($id);

        return view('adminc.sales_leads.edit', ['salesLead' => $salesLead]);
    }

    public function update(Request $request, $id)
    {
        $request->validate($this->getValidationRules());

        $salesLead = SalesLead::findOrFail($id);
        $salesLead->update($this->prepareSalesLeadData($request->all()));

        // Handle person relationships - always sync if person_ids is in request, even if empty array
        if ($request->has('person_ids')) {
            $personIds = RequestHelper::filterIntegerArray($request, 'person_ids');
            $salesLead->syncPersons($personIds);
        }

        // If this is an AJAX request (like from kanban drag & drop), return JSON
        if ($request->ajax()) {
            return response()->json([
                'message'    => __('messages.sales.updated'),
                'sales_lead' => $salesLead,
            ]);
        }

        return redirect()->route('admin.sales-leads.view', $id)
            ->with('success', __('messages.sales.updated'));
    }

    public function view($id)
    {
        // First load the Sales to check if it has a lead_id
        $salesLead = SalesLead::with(['stage.pipeline.stages', 'user', 'lead', 'persons'])->findOrFail($id);

        // If there's no related lead_id, return 404
        if (! $salesLead->lead_id) {
            Log::warning('SalesLead found but no lead_id set', [
                'sales_lead_id'   => $id,
                'sales_lead_name' => $salesLead->name,
                'sales_lead_data' => $salesLead->toArray(),
            ]);

            return response()->view('errors.404', [
                'message' => 'Deze Sales heeft geen gekoppelde lead. Sales: '.$salesLead->name,
            ], 404);
        }
        $lead = Lead::findOrFail($salesLead->lead_id);
        if (! $lead) {
            Log::warning('Lead not found for SalesLead', [
                'sales_lead_id' => $id,
                'lead_id'       => $salesLead->lead_id,
            ]);

            return response()->view('errors.404', [
                'message' => 'Lead met ID '.$salesLead->lead_id.' niet gevonden.',
            ], 404);
        }

        // Load related orders
        $orders = Order::where('sales_lead_id', $salesLead->id)->get();

        // Collect emails from associated persons
        $emails = [];
        if ($salesLead->persons && $salesLead->persons->count() > 0) {
            $emails[] = [
                'value'      => $salesLead->defaultEmailContactPerson() ?: '',
                'is_default' => true,
            ];
        }
        $activitiesCount = $this->activityRepository->countOpen($salesLead)->getData()->data;

        return view('adminc.sales_leads.view', [
            'salesLead'      => $salesLead,
            'lead'           => $lead,
            'orders'         => $orders,
            'emails'         => $emails,
            'activitiesCount'=> $activitiesCount,
        ]);
    }

    public function updateStage($id)
    {
        request()->validate([
            'lead_pipeline_stage_id' => 'required|exists:lead_pipeline_stages,id',
            'lost_reason'            => ['nullable', new Enum(LostReason::class)],
            'closed_at'              => 'nullable',
        ]);

        $salesLead = SalesLead::findOrFail($id);
        $targetStage = StageProxy::findOrFail((int) request('lead_pipeline_stage_id'));

        // Optionally close open activities for this Sales when requested (parity with lead stage update)
        if (request()->boolean('close_open_activities')) {
            Activity::where('sales_lead_id', $salesLead->id)
                ->where('is_done', 0)
                ->update(['is_done' => 1]);
        }

        $attributes = [
            'pipeline_stage_id' => $targetStage->id,
            'closed_at'         => StageTransitionAttributes::resolveClosedAt($targetStage, request('closed_at')),
        ];

        if ($targetStage->is_lost) {
            request()->validate([
                'lost_reason' => ['required', new Enum(LostReason::class)],
            ]);
        }

        $attributes['lost_reason'] = StageTransitionAttributes::resolveLostReason($targetStage, request('lost_reason'));

        $salesLead->update($attributes);

        return response()->json([
            'message' => __('messages.sales.stage_updated'),
        ]);
    }

    /**
     * Mark Sales as lost with reason and closed_at; do not touch linked lead.
     */
    public function lost($id)
    {
        request()->validate([
            'lost_reason' => ['required', new Enum(LostReason::class)],
            'closed_at'   => 'nullable|date',
        ]);

        $salesLead = SalesLead::findOrFail($id);

        // Find the lost stage for this Sales's pipeline (relations required)
        $stage = $salesLead->stage;
        if (! $stage) {
            return response()->json([
                'message' => 'Sales heeft geen pipeline stage.',
            ], 422);
        }

        $pipeline = $stage->pipeline;
        if (! $pipeline) {
            return response()->json([
                'message' => 'Pipeline niet gevonden voor de huidige stage.',
            ], 422);
        }

        $lostStage = $pipeline->stages()->where('is_lost', true)->first();

        if (! $lostStage) {
            return response()->json([
                'message' => 'Geen "Verloren" status gevonden voor deze pipeline.',
            ], 422);
        }

        // Update lead to lost status
        $leadData = [
            'pipeline_stage_id' => $lostStage->id,
            'lost_reason'       => request('lost_reason'),
            'closed_at'         => request('closed_at') ?: now(),
        ];

        $salesLead->update($leadData);

        // Complete all open activities for this lead
        $this->completeAllOpenActivitiesForLead($salesLead->id);

        return response()->json([
            'message' => 'Sales is afgevoerd.',
        ]);
    }

    public function activities($id)
    {
        $salesLead = SalesLead::findOrFail($id);

        $isDoneFilter = request()->has('is_done') ? (int) request('is_done') : null;
        $withHierarchy = request()->has('hierarchy') ? filter_var(request('hierarchy'), FILTER_VALIDATE_BOOLEAN) : true;

        // 1. Own sales activities — label: 'Sales'
        $salesQuery = Activity::where('sales_lead_id', $id);
        if (! is_null($isDoneFilter)) {
            $salesQuery->where('is_done', $isDoneFilter);
        }
        $salesActivities = $salesQuery->get();
        $salesActivities->each(fn ($a) => $a->entity_source = [
            'type'  => 'sales',
            'label' => 'Sales',
        ]);

        // 2. Child order activities — label: 'Order: {title}'
        $orderActivities = collect();
        if ($withHierarchy) {
            $orderIds = $salesLead->orders()->pluck('orders.id');
            if ($orderIds->isNotEmpty()) {
                $orderTitles = Order::whereIn('id', $orderIds)->pluck('title', 'id');
                $orderQuery = Activity::whereIn('order_id', $orderIds);
                if (! is_null($isDoneFilter)) {
                    $orderQuery->where('is_done', $isDoneFilter);
                }
                $orderActivities = $orderQuery->get();
                $orderActivities->each(fn ($a) => $a->entity_source = [
                    'type'  => 'order',
                    'label' => 'Order: '.($orderTitles[$a->order_id] ?? $a->order_id),
                ]);
            }
        }

        $all = $salesActivities->merge($orderActivities)->unique('id')->values();
        $merged = $this->concatEmailActivitiesFor('sales', (int) $id, $all, $this->attachmentRepository);

        if (! is_null($isDoneFilter)) {
            $merged = $merged->filter(fn ($a) => (int) $a->is_done === $isDoneFilter)->values();
        }

        return ActivityResource::collection($merged);
    }

    public function storeActivity($id)
    {
        request()->validate([
            'type'          => 'required|in:task,meeting,call',
            'title'         => 'required|string',
            'description'   => 'nullable|string',
            'user_id'       => 'nullable|exists:users,id',
            'schedule_from' => 'required_unless:type,note,file|date_format:Y-m-d H:i:s',
            'schedule_to'   => 'required_unless:type,note,file|date_format:Y-m-d H:i:s',
        ]);

        $activity = Activity::create([
            'type'             => request('type'),
            'title'            => request('title'),
            'comment'          => request('description'),
            'user_id'          => request('user_id') ?? auth()->id(),
            'sales_lead_id'    => $id,
            'schedule_from'    => request('schedule_from'),
            'schedule_to'      => request('schedule_to'),
            'is_done'          => 0,
        ]);

        return response()->json([
            'message' => __('messages.sales.activity_created'),
            'data'    => $activity,
        ]);
    }

    public function detachEmail($id, $emailId)
    {
        // Placeholder for email detachment
        return response()->json([
            'message' => __('messages.sales.email_detached'),
        ]);
    }

    public function delete($id)
    {
        $salesLead = SalesLead::findOrFail($id);
        $salesLead->delete();

        return redirect()->route('admin.sales-leads.index')
            ->with('success', __('messages.sales.deleted'));
    }

    /**
     * Search Sales.
     * Uses minimal resource to avoid N+1 queries.
     */
    public function search(): AnonymousResourceCollection|JsonResponse
    {
        return $this->performAdvancedSearch(
            repository: $this->salesLeadRepository,
            getFieldsSearchable: fn () => $this->salesLeadRepository->getFieldsSearchable(),
            eagerLoadRelations: ['stage', 'user'],
            getResults: function ($repository) {
                // Always apply RequestCriteria (with normalized search/searchFields)
                $repository->pushCriteria(app(RequestCriteria::class));

                // Apply permission filter via a composable Criteria (no scopeQuery to avoid overwriting)
                $this->applyPermissionFilter($repository);

                return $repository->all();
            },
            resourceClass: SalesLeadLookupResource::class,
            queryParams: request()->query->all()
        );
    }

    /**
     * Temporary debug method to test data structure
     */
    public function debug($id)
    {
        $salesLead = SalesLead::with(['stage', 'lead', 'user'])->findOrFail($id);

        $person = $salesLead->persons()->first();

        return response()->json([
            'sales_lead' => [
                'id'          => $salesLead->id,
                'name'        => $salesLead->name,
                'description' => $salesLead->description,
                'lead'        => $salesLead->lead ? [
                    'id'     => $salesLead->lead->id,
                    'title'  => $salesLead->lead->title,
                    'person' => $person ? [
                        'id'   => $person->id,
                        'name' => $person->name,
                    ] : null,
                ] : null,
                'user' => $salesLead->user ? [
                    'id'   => $salesLead->user->id,
                    'name' => $salesLead->user->name,
                ] : null,
            ],
        ]);
    }

    /**
     * Get search configuration for sales.
     */
    protected function getSearchConfig(): array
    {
        return [
            'name_fields'                 => ['name'], // SalesLead only has 'name', not first_name/last_name
            'supports_email_phone_search' => false, // SalesLead doesn't have emails/phones columns
            'supports_user_name_search'   => false,
            'enable_debug_logging'        => $this->enableDebugLogging,
            'table_name'                  => 'salesleads',
        ];
    }

    /**
     * Get searchable fields for SalesLead.
     */
    protected function getSalesLeadSearchableFields(): array
    {
        return [
            'name',
            'description',
            'user_id',
            'user.name',
            'user.first_name',
            'user.last_name',
            'pipeline_stage_id',
            'created_at',
            'closed_at',
        ];
    }

    /**
     * Complete all open activities for a lead
     */
    private function completeAllOpenActivitiesForLead(int $salesLeadId): void
    {
        $activityRepository = app(ActivityRepository::class);

        $openActivities = $activityRepository
            ->where('sales_lead_id', $salesLeadId)
            ->where('is_done', 0)
            ->get();

        foreach ($openActivities as $activity) {
            $activityRepository->update([
                'is_done' => 1,
                'status'  => ActivityStatus::DONE->value,
            ], $activity->id);
        }
    }

    /**
     * Prepare Sales data for creation/update.
     *
     * Handles contact_person_id logic:
     * 1. If contact_person_id_display has a value, use it for contact_person_id
     * 2. Convert empty strings or '0' to null for database consistency
     * 3. Remove the display field to prevent database errors
     */
    private function prepareSalesLeadData(array $data): array
    {
        // Handle contact_person_id_display -> contact_person_id mapping
        if (isset($data['contact_person_id_display']) && ! empty($data['contact_person_id_display'])) {
            $data['contact_person_id'] = $data['contact_person_id_display'];
            unset($data['contact_person_id_display']); // Remove display field to prevent DB errors
        }

        // Normalize contact_person_id - convert empty values to null
        if (isset($data['contact_person_id']) && (empty($data['contact_person_id']) || $data['contact_person_id'] === '0')) {
            $data['contact_person_id'] = null;
        }

        return $data;
    }

    private function getValidationRules(bool $isCreate = false): array
    {
        $leadRule = ($isCreate) ? 'required|exists:leads,id' : 'nullable|exists:leads,id';

        // person_ids is optional on create (persons are copied from lead if not provided)
        // but required with min:1 on update (at least one person must remain)
        $personIdsRule = $isCreate ? 'nullable|array' : 'required|array|min:1';

        return [
            'name'                      => 'required|string|max:255',
            'description'               => 'nullable|string',
            'pipeline_stage_id'         => 'nullable|exists:lead_pipeline_stages,id',
            'lead_id'                   => $leadRule,
            'user_id'                   => 'nullable|exists:users,id',
            'contact_person_id'         => 'nullable|exists:persons,id',
            'contact_person_id_display' => 'nullable|string',
            'person_ids'                => $personIdsRule,
            'person_ids.*'              => 'exists:persons,id',
        ];
    }
}
