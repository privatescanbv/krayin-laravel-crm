<?php

namespace Webkul\Admin\Http\Controllers\Lead;

use App\Enums\LostReason;
use App\Enums\ActivityStatus;
use App\Enums\PipelineDefaultKeys;
use App\Http\Controllers\Concerns\NormalizesContactFields;
use App\Models\Address;
use App\Models\Anamnesis;
use App\Models\Department;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Prettus\Repository\Criteria\RequestCriteria;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Admin\DataGrids\Lead\LeadDataGrid;
use Webkul\Admin\Http\Controllers\Contact\Persons\PersonController;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\LeadForm;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Requests\MassUpdateRequest;
use Webkul\Admin\Http\Resources\LeadResource;
use Webkul\Admin\Http\Resources\LeadKanbanResource;
use Webkul\Admin\Http\Resources\StageResource;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Helpers\MagicAI;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Lead\Repositories\SourceRepository;
use Webkul\Lead\Repositories\StageRepository;
use Webkul\Lead\Repositories\TypeRepository;
use Webkul\Lead\Services\MagicAIService;
use Webkul\Tag\Repositories\TagRepository;
use Webkul\User\Repositories\UserRepository;
use InvalidArgumentException;
use App\Services\LeadValidationService;
use App\Services\PipelineCookieService;
use App\Services\LeadStatusTransitionValidator;
use App\Repositories\AddressRepository;
use App\Services\UserDefaultValueService;

class LeadController extends Controller
{
    use NormalizesContactFields;

    /**
     * Const variable for supported types.
     */
    const SUPPORTED_TYPES = 'pdf,bmp,jpeg,jpg,png,webp';

    /**
     * Const variable for won/lost stage codes.
     */
    const WON_LOST_STAGE_CODES = ['won', 'lost', 'won-hernia', 'lost-hernia'];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected UserRepository      $userRepository,
        protected AttributeRepository $attributeRepository,
        protected SourceRepository    $sourceRepository,
        protected TypeRepository      $typeRepository,
        protected PipelineRepository  $pipelineRepository,
        protected StageRepository     $stageRepository,
        protected LeadRepository      $leadRepository,
        protected PersonRepository    $personRepository,
        protected AddressRepository   $addressRepository,
        protected PipelineCookieService $pipelineCookieService,
        protected UserDefaultValueService $userDefaultValueService
    )
    {
        request()->request->add(['entity_type' => 'leads']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->ajax()) {
            return datagrid(LeadDataGrid::class)->process();
        }

        // Get effective pipeline ID (URL parameter takes precedence over cookie)
        $effectivePipelineId = $this->pipelineCookieService->getEffectivePipelineId(request('pipeline_id'));

        if ($effectivePipelineId) {
            $pipeline = $this->pipelineRepository->find($effectivePipelineId);
        }

        // Fall back to default pipeline if no valid pipeline found
        if (!isset($pipeline) || !$pipeline) {
            $pipeline = $this->pipelineRepository->getDefaultPipeline();
            // Set cookie for default pipeline
            if ($pipeline) {
                $this->pipelineCookieService->setLastSelectedPipelineId($pipeline->id);
            }
        }

        return view('admin::leads.index', [
            'pipeline' => $pipeline,
            'columns' => $this->getKanbanColumns(),
        ]);
    }

    /**
     * Returns a listing of the resource.
     */
    public function get(): JsonResponse
    {
        try {
            // Get effective pipeline ID (URL parameter takes precedence over cookie)
            $effectivePipelineId = $this->pipelineCookieService->getEffectivePipelineId(request()->query('pipeline_id'));

            if ($effectivePipelineId) {
                $pipeline = $this->pipelineRepository->find($effectivePipelineId);
            }

            // Fall back to default pipeline if no valid pipeline found
            if (!isset($pipeline) || !$pipeline) {
                $pipeline = $this->pipelineRepository->getDefaultPipeline();
            }

            if (!$pipeline) {
                Log::error('No pipeline found for leads.get endpoint', [
                    'pipeline_id' => request()->query('pipeline_id'),
                    'request_params' => request()->all()
                ]);

                return response()->json([
                    'error' => 'No pipeline found',
                    'message' => 'Could not find the specified pipeline'
                ], 500);
            }

        // Check if we should exclude won/lost stages for performance optimization
        $excludeWonLost = filter_var(request()->query('exclude_won_lost', false), FILTER_VALIDATE_BOOLEAN);

        if (request()->query('pipeline_stage_id')) {
            $stages = $pipeline->stages()->select('id','code','name','sort_order','lead_pipeline_id','is_won','is_lost')->where('id', request()->query('pipeline_stage_id'))->get();
        } else {
            $stages = $pipeline->stages()->select('id','code','name','sort_order','lead_pipeline_id','is_won','is_lost')->get();

            // Filter out won/lost stages if requested to improve performance
            if ($excludeWonLost) {
                $stages = $stages->filter(function ($stage) {
                    return !($stage->is_won || $stage->is_lost);
                });
            }
        }

        // Normalize kanban search params: map `name` to first/last/married_name
        $search = request()->query('search', '');
        $searchFields = request()->query('searchFields', '');
        if ($search && $searchFields && (str_contains($searchFields, 'name') || str_contains($search, 'name:'))) {
            $matches = [];
            preg_match_all('/name:([^;]+);?/i', $search, $matches);
            $term = isset($matches[1][0]) ? trim($matches[1][0]) : '';
            if ($term !== '') {
                request()->merge([
                    'search' => "first_name:{$term};last_name:{$term};married_name:{$term}",
                    'searchFields' => 'first_name:like;last_name:like;married_name:like',
                    'searchJoin' => 'or',
                ]);
            }
        }

        // Compute total leads per stage in one DB query to avoid running pagination on empty stages
        $stageIds = $stages->pluck('id')->all();
        $totalsByStage = DB::table('leads')
            ->select('lead_pipeline_stage_id', DB::raw('COUNT(*) as total'))
            ->where('lead_pipeline_id', $pipeline->id)
            ->whereIn('lead_pipeline_stage_id', $stageIds)
            ->groupBy('lead_pipeline_stage_id')
            ->pluck('total', 'lead_pipeline_stage_id');

        // Build response for each stage with per-stage pagination only when needed
        foreach ($stages as $stage) {
            // Key by stage ID to ensure all columns (including empty ones) render consistently
            $data[$stage->id] = (new StageResource($stage))->jsonSerialize();

            $totalForStage = (int) ($totalsByStage[$stage->id] ?? 0);

            if ($totalForStage > 0) {
                $query = app(LeadRepository::class)
                    ->pushCriteria(app(RequestCriteria::class))
                    ->where([
                        'lead_pipeline_id' => $pipeline->id,
                        'lead_pipeline_stage_id' => $stage->id,
                    ]);

                if ($userIds = bouncer()->getAuthorizedUserIds()) {
                    $query->whereIn('leads.user_id', $userIds);
                }
                $paginator = $query->select([
                    'leads.id',
                    'leads.first_name',
                    'leads.last_name',
                    'leads.married_name',
                    'leads.lastname_prefix',
                    'leads.salutation',
                    'leads.created_at',
                    'leads.married_name_prefix',
                    'leads.lead_pipeline_id',
                    'leads.lead_pipeline_stage_id',
                    'leads.mri_status',
                    'leads.lost_reason',
                    'leads.has_diagnosis_form',
                    DB::raw('COALESCE(open_activities.open_activity_count, 0) AS open_activities_count_query'),
                    DB::raw('COALESCE(open_emails.open_email_count, 0) AS open_email_count_query'),
                ])->with([
                    'stage:id,code,name,sort_order,is_won,is_lost',
                    // Removed persons eager loading to prevent null pivot issues
                    // Removed pipeline eager loading to prevent N+1 per stage
                ])->leftJoin(DB::raw('(
                        SELECT lead_id, COUNT(*) AS open_activity_count
                        FROM activities
                        WHERE is_done = 0
                        GROUP BY lead_id
                    ) AS open_activities'), 'open_activities.lead_id', '=', 'leads.id')
                ->leftJoin(DB::raw('(
                        SELECT lead_id, COUNT(*) AS open_email_count
                        FROM emails
                        WHERE is_read = 0
                        GROUP BY lead_id
                    ) AS open_emails'), 'open_emails.lead_id', '=', 'leads.id')
                ->with([
                    'stage:id,code,name,sort_order,is_won,is_lost',
                ])
                ->orderBy('leads.created_at', 'desc')
                ->paginate((int) request()->query('limit', 10));
                // Set pipeline relation manually to prevent N+1 queries in getRottenDaysAttribute
                $pipelineModel = new Pipeline([
                    'id' => $pipeline->id,
                    'rotten_days' => $pipeline->rotten_days
                ]);
                foreach ($paginator->items() as $lead) {
                    $lead->setRelation('pipeline', $pipelineModel);
                }

                $data[$stage->id]['leads'] = [
                    'data' => LeadKanbanResource::collection($paginator),

                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'from' => $paginator->firstItem(),
                        'last_page' => $paginator->lastPage(),
                        'per_page' => $paginator->perPage(),
                        'to' => $paginator->lastItem(),
                        'total' => $totalForStage, // use precomputed total
                    ],
                ];
            } else {
                // Empty stage: still return column with empty leads
                $data[$stage->id]['leads'] = [
                    'data' => [],
                    'meta' => [
                        'current_page' => 1,
                        'from' => null,
                        'last_page' => 1,
                        'per_page' => 0,
                        'to' => 0,
                        'total' => 0,
                    ],
                ];
            }
        }

        return response()->json($data);

        } catch (Exception $e) {
            Log::error('Error in leads.get endpoint', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_params' => request()->all(),
                'exclude_won_lost' => $excludeWonLost ?? null
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        // Get current user's groups to determine default department
        $user = auth()->guard('user')->user() ?? auth()->user();
        $userGroupNames = $user->groups->pluck('name')->toArray();
        $defaultDepartmentId = Department::mapGroupToDepartmentId($userGroupNames);

        // Get user default values for lead fields
        $userDefaults = $this->userDefaultValueService->getLeadDefaults($user->id);
        logger()->info('User default lead values', ['user_id' => $user->id, 'defaults' => $userDefaults]);

        // Get effective pipeline ID (URL parameter takes precedence over cookie)
        $effectivePipelineId = $this->pipelineCookieService->getEffectivePipelineId(request('pipeline_id'));

        // Determine pipeline and stage based on request parameters and cookie
        $pipelineData = $this->determinePipelineForLead(
            $effectivePipelineId,
            request('stage_id'),
            $defaultDepartmentId
        );

        $pipeline = $pipelineData['pipeline'];
        $defaultStageId = $pipelineData['stage_id'];
        $defaultDepartmentId = $pipelineData['department_id'];

        // Preselect person if provided via query param
        $prefilledPersons = [];
        $prefilledLeadPerson = null;
        $personId = (int) request('person_id');
        if ($personId) {
            try {
                $person = $this->personRepository->find($personId);
                if ($person) {
                    $prefilledPersons[] = [
                        'id' => $person->id,
                        'name' => $person->name,
                        'first_name' => $person->first_name,
                        'last_name' => $person->last_name,
                        'lastname_prefix' => $person->lastname_prefix,
                        'married_name' => $person->married_name,
                        'married_name_prefix' => $person->married_name_prefix,
                        'initials' => $person->initials,
                        'emails' => is_array($person->emails) ? $person->emails : [],
                        'phones' => is_array($person->phones) ? $person->phones : [],
                        'organization' => $person->organization ? [
                            'id' => $person->organization->id,
                            'name' => $person->organization->name,
                        ] : null,
                    ];

                    // Prefill full personal fields for step 2
                    $prefilledLeadPerson = [
                        'salutation' => $person->salutation,
                        'initials' => $person->initials,
                        'first_name' => $person->first_name,
                        'lastname_prefix' => $person->lastname_prefix,
                        'last_name' => $person->last_name,
                        'married_name_prefix' => $person->married_name_prefix,
                        'married_name' => $person->married_name,
                        'date_of_birth' => $person->date_of_birth,
                        'gender' => $person->gender,
                        'emails' => is_array($person->emails) ? $person->emails : [],
                        'phones' => is_array($person->phones) ? $person->phones : [],
                    ];
                }
            } catch (Exception $e) {
                // Ignore if person not found
            }
        }

        return view('admin::leads.create', [
            'defaultDepartmentId' => $defaultDepartmentId,
            'defaultPipelineId' => $pipeline->id ?? null,
            'defaultStageId' => $defaultStageId,
            'departmentOptions' => Department::pluck('name', 'id')->toArray(),
            'prefilledPersons' => $prefilledPersons,
            'prefilledLeadPerson' => $prefilledLeadPerson,
            'userDefaults' => $userDefaults,
            'currentUserId' => $user->id,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LeadForm $request): RedirectResponse|JsonResponse
    {
        try {
            // Normalize contact arrays before validation
            $this->normalizeContactArrays($request);

            // Validate with custom rules including email/phone requirement
            $this->validate($request, LeadValidationService::getWebValidationRules($request));

            // Additional rule now embedded in LeadValidationService rules

            [$lead, $leadPipelineId] = $this->storeLead($request);

            // Check if we should redirect to sync page after creation
            $shouldSync = $this->shouldRedirectToSync($lead);
            if ($shouldSync) {
                $person = $lead->persons()->first();
                if (request()->ajax()) {
                    return response()->json([
                        'message'  => trans('admin::app.leads.create-success'),
                        'redirect' => route('admin.leads.sync-lead-to-person', [
                            'leadId' => $lead->id,
                            'personId' => $person->id
                        ]),
                    ]);
                }

                session()->flash('success', trans('admin::app.leads.create-success'));
                return redirect()->route('admin.leads.sync-lead-to-person', [
                    'leadId' => $lead->id,
                    'personId' => $person->id
                ]);
            }

            // If this is an AJAX request, respond with JSON containing redirect target
            if (request()->ajax()) {
                return response()->json([
                    'message'  => trans('admin::app.leads.create-success'),
                    'redirect' => route('admin.leads.view', $lead->id),
                ]);
            }

            session()->flash('success', trans('admin::app.leads.create-success'));

            // Na aanmaken: ga naar de lead detailpagina in plaats van het kanban bord
            return redirect()->route('admin.leads.view', $lead->id);
        } catch (InvalidArgumentException $e) {
            if (request()->ajax()) {
                throw new ValidationException(
                    Validator::make([], []),
                    response()->json([
                        'message' => $e->getMessage(),
                    ], 422)
                );
            }

            return redirect()->back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * @param LeadForm $request
     * @throws InvalidArgumentException validation
     * @return array
     */
    public function storeLead(LeadForm $request): array
    {
            Event::dispatch('lead.create.before');

            $data = $request->all();

            // Normalize empty strings and placeholders to null for foreign keys and enums
            foreach (['user_id', 'organization_id', 'lead_channel_id', 'lead_source_id', 'lead_type_id'] as $nullableKey) {
                if (array_key_exists($nullableKey, $data)) {
                    if ($data[$nullableKey] === '' || $data[$nullableKey] === '?' || $data[$nullableKey] === null) {
                        $data[$nullableKey] = null;
                    }
                }
            }
            foreach (['salutation', 'gender', 'mri_status'] as $enumKey) {
                if (array_key_exists($enumKey, $data)) {
                    if ($data[$enumKey] === '' || $data[$enumKey] === '?') {
                        $data[$enumKey] = null;
                    }
                }
            }

            // Handle empty date field
            if (array_key_exists('date_of_birth', $data) && ($data['date_of_birth'] === '' || $data['date_of_birth'] === null)) {
                $data['date_of_birth'] = null;
            }

            // Contact normalization is now handled by normalizeContactFields() in create() method

            $data['status'] = 1;

            if (isset($data['lead_pipeline_stage_id'])) {
                $stage = $this->stageRepository->findOrFail($data['lead_pipeline_stage_id']);

                // If pipeline_id is also provided, validate that stage belongs to pipeline
                if (isset($data['lead_pipeline_id']) && $stage->lead_pipeline_id != $data['lead_pipeline_id']) {
                    throw new InvalidArgumentException('The selected stage does not belong to the specified pipeline.');
                }

                $data['lead_pipeline_id'] = $stage->lead_pipeline_id;
            } else {
                // Determine pipeline based on department_id if provided
                $pipeline = null;

                if (isset($data['department_id'])) {
                    if ($data['department_id'] == Department::findHerniaId()) {
                        $pipeline = $this->pipelineRepository->find(PipelineDefaultKeys::PIPELINE_HERNIA_ID->value);
                    } elseif ($data['department_id'] == Department::findPrivateScanId()) {
                        $pipeline = $this->pipelineRepository->find(PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value);
                    }
                }

                // Fall back to default pipeline if not found
                if (!$pipeline) {
                    $pipeline = $this->pipelineRepository->getDefaultPipeline();
                }

                $stage = $pipeline->stages()->first();

                $data['lead_pipeline_id'] = $pipeline->id;

                $data['lead_pipeline_stage_id'] = $stage->id;
            }

            if ($stage->is_won || $stage->is_lost) {
                $data['closed_at'] = Carbon::now();
            }
            $lead = $this->leadRepository->create($data);

            // Validate and persist address if provided on lead create
            try {
                if (isset($data['address']) && is_array($data['address'])) {
                    $this->addressRepository->upsertForLead($lead->id, $data['address']);
                }
            } catch (Exception $e) {
                // Re-throw as InvalidArgumentException to align with request handling above
                throw new InvalidArgumentException($e->getMessage());
            }

            // Persist basic anamnesis answers if provided and if persons attached
            try {
                if (!empty($data['persons']) || !empty($data['person_ids'])) {
                    $anamnesisUpdate = [];
                    if (array_key_exists('metals', $data)) {
                        $anamnesisUpdate['metals'] = (bool) $data['metals'];
                    }
                    if (!empty($data['metals_notes'])) {
                        $anamnesisUpdate['metals_notes'] = $data['metals_notes'];
                    }
                    if (array_key_exists('claustrophobia', $data)) {
                        $anamnesisUpdate['claustrophobia'] = (bool) $data['claustrophobia'];
                    }
                    if (array_key_exists('allergies', $data)) {
                        $anamnesisUpdate['allergies'] = (bool) $data['allergies'];
                    }
                    if (!empty($data['allergies_notes'])) {
                        $anamnesisUpdate['allergies_notes'] = $data['allergies_notes'];
                    }
                    if (isset($data['height']) && $data['height'] !== '' && $data['height'] !== null) {
                        $anamnesisUpdate['height'] = $data['height'];
                    }
                    if (isset($data['weight']) && $data['weight'] !== '' && $data['weight'] !== null) {
                        $anamnesisUpdate['weight'] = $data['weight'];
                    }

                    if (!empty($anamnesisUpdate)) {
                        // Update all related anamnesis for this lead
                        foreach ($lead->persons as $person) {
                            Anamnesis::where('lead_id', $lead->id)
                                ->where('person_id', $person->id)
                                ->update($anamnesisUpdate);
                        }
                    }
                }
            } catch (Exception $e) {
                Log::warning('Failed to persist anamnesis answers on lead create', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);
            }

            Event::dispatch('lead.create.after', $lead);

            return [$lead, $data['lead_pipeline_id']];
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View
    {
        // Load lead without persons relationship (persons loaded via attribute)
        $lead = $this->leadRepository->with(['address', 'organization', 'contactPerson'])->findOrFail($id);

        return view('admin::leads.edit', compact('lead'));
    }

    /**
     * Display a resource.
     */
    public function view(int $id)
    {
        $lead = $this->leadRepository->with([
            'address',
            'organization',
            'source',
            'type',
            'channel',
            'department',
            'user',
        ])->findOrFail($id);

        $userIds = bouncer()->getAuthorizedUserIds();

        if (
            $userIds
            && !in_array($lead->user_id, $userIds)
        ) {
            // Get last selected pipeline from cookie to preserve selection
            $lastPipelineId = $this->pipelineCookieService->getLastSelectedPipelineId();
            $routeParams = $lastPipelineId ? ['pipeline_id' => $lastPipelineId] : [];
            return redirect()->route('admin.leads.index', $routeParams);
        }

        return view('admin::leads.view', compact('lead'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(LeadForm $request, int $id): RedirectResponse|JsonResponse
    {
        // Normalize contact fields before validation
        $this->normalizeContactFields($request);

        try {
            try {
                $validationRules = LeadValidationService::getWebValidationRules($request, false);
                $validationRules['contact_person_id_display'] = 'nullable|string';
                $this->validate($request, $validationRules);
            } catch (ValidationException $exception) {
                // for missing error displaying in the UI
                logger()->warning('Validation error during lead update', ['errors' => $exception->errors()]);
                throw $exception;
            }
            // Additional rule now embedded in LeadValidationService rules
            Event::dispatch('lead.update.before', $id);

            $data = $request->all();

            // Handle contact_person_id - if empty but display has value, use display value
            if (isset($data['contact_person_id_display']) && !empty($data['contact_person_id_display'])) {
                $data['contact_person_id'] = $data['contact_person_id_display'];
                unset($data['contact_person_id_display']); // Remove the display field
            }

            // Handle contact_person_id - convert empty string to null
            if (isset($data['contact_person_id']) && (empty($data['contact_person_id']) || $data['contact_person_id'] === '0')) {
                $data['contact_person_id'] = null;
            }

            // Handle empty date field
            if (array_key_exists('date_of_birth', $data) && ($data['date_of_birth'] === '' || $data['date_of_birth'] === null)) {
                $data['date_of_birth'] = null;
            }

            // Contact normalization is now handled by normalizeContactFields() in update() method

            if (isset($data['lead_pipeline_stage_id'])) {
                $stage = $this->stageRepository->findOrFail($data['lead_pipeline_stage_id']);

                // If pipeline_id is also provided, validate that stage belongs to pipeline
                if (isset($data['lead_pipeline_id']) && $stage->lead_pipeline_id != $data['lead_pipeline_id']) {
                    throw new \InvalidArgumentException('The selected stage does not belong to the specified pipeline.');
                }

                $data['lead_pipeline_id'] = $stage->lead_pipeline_id;
            } else {
                // Determine pipeline based on department_id if provided
                $pipeline = null;

                if (isset($data['department_id'])) {
                    if ($data['department_id'] == Department::findHerniaId()) {
                        $pipeline = $this->pipelineRepository->find(PipelineDefaultKeys::PIPELINE_HERNIA_ID->value);
                    } elseif ($data['department_id'] == Department::findPrivateScanId()) {
                        $pipeline = $this->pipelineRepository->find(PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value);
                    }
                }

                // Fall back to default pipeline if not found
                if (!$pipeline) {
                    $pipeline = $this->pipelineRepository->getDefaultPipeline();
                }

                $stage = $pipeline->stages()->first();

                $data['lead_pipeline_id'] = $pipeline->id;

                $data['lead_pipeline_stage_id'] = $stage->id;
            }

            $lead = $this->leadRepository->update($data, $id);

            // Validate and persist address if provided on lead update
            try {
                if (isset($data['address']) && is_array($data['address'])) {
                    $this->addressRepository->upsertForLead($lead->id, $data['address']);
                }
            } catch (Exception $e) {
                throw new InvalidArgumentException($e->getMessage());
            }

            Event::dispatch('lead.update.after', $lead);

            // Check if we should redirect to sync page
            $shouldSync = $this->shouldRedirectToSync($lead);
            if ($shouldSync) {
                $person = $lead->persons()->first();
                if (request()->ajax()) {
                    return response()->json([
                        'message'  => trans('admin::app.leads.update-success'),
                        'redirect' => route('admin.leads.sync-lead-to-person', [
                            'leadId' => $lead->id,
                            'personId' => $person->id
                        ]),
                    ]);
                }

                session()->flash('success', trans('admin::app.leads.update-success'));
                return redirect()->route('admin.leads.sync-lead-to-person', [
                    'leadId' => $lead->id,
                    'personId' => $person->id
                ]);
            }

            if (request()->ajax()) {
                return response()->json([
                    'message'  => trans('admin::app.leads.update-success'),
                    'redirect' => route('admin.leads.view', $lead->id),
                ]);
            }

            session()->flash('success', trans('admin::app.leads.update-success'));
            // After edit: go to lead view page
            return redirect()->route('admin.leads.view', $lead->id);
        } catch (InvalidArgumentException $e) {
            if (request()->ajax()) {
                throw new ValidationException(
                    Validator::make([], []),
                    response()->json([
                        'message' => $e->getMessage(),
                    ], 422)
                );
            }
            return redirect()->back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Update the lead stage.
     */
    public function updateStage(int $leadId): JsonResponse
    {
        $this->validate(request(), [
            'lead_pipeline_stage_id' => 'required|exists:lead_pipeline_stages,id',
            'lost_reason' => ['nullable', new Enum(LostReason::class)],
            'closed_at' => 'nullable|date',
        ]);

        $data = [
            'lead_pipeline_stage_id' => request()->input('lead_pipeline_stage_id'),
        ];

        // Add optional fields if provided
        if (request()->has('lost_reason')) {
            $data['lost_reason'] = request()->input('lost_reason');
        }

        if (request()->has('closed_at')) {
            $data['closed_at'] = request()->input('closed_at');
        }

        // Optional flag to close open activities when changing stage
        if (request()->has('close_open_activities')) {
            $data['close_open_activities'] = (bool) request()->input('close_open_activities');
        }

        return $this->updateStageWithData($leadId, $data);
    }

    /**
     * Update the lead stage with additional data.
     */
    public function updateStageWithData(int $leadId, array $data): JsonResponse
    {
        $lead = $this->leadRepository->findOrFail($leadId);

        //validate if the stage exists in the pipeline
        $lead->pipeline->stages()
            ->where('id', $data['lead_pipeline_stage_id'])
            ->firstOrFail();

        // Validate status transition if stage is being changed
        if (isset($data['lead_pipeline_stage_id']) &&
            $data['lead_pipeline_stage_id'] != $lead->lead_pipeline_stage_id) {

            try {
                LeadStatusTransitionValidator::validateTransition($lead, $data['lead_pipeline_stage_id']);
            } catch (ValidationException $e) {
                return response()->json([
                    'message' => 'Status transitie validatie gefaald: '.$e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }
        }

        Event::dispatch('lead.update.before', $leadId);

        $lead = $this->leadRepository->update(
            array_merge($data, ['entity_type' => 'leads']),
            $leadId,
            array_keys($data)
        );

        // If requested, complete all open activities for this lead after successful update
        if (!empty($data['close_open_activities'])) {
            try {
                $this->completeAllOpenActivitiesForLead($leadId);
            } catch (Exception $e) {
                // Log but don't fail the stage update response
                Log::warning('Failed to complete open activities during stage update', [
                    'lead_id' => $leadId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Event::dispatch('lead.update.after', $lead);

        return response()->json([
            'message' => trans('admin::app.leads.update-success'),
        ]);
    }

    /**
     * Update the lead stage.
     */
    public function updateStageId(int $leadId, string $nextPipelineStageId): JsonResponse
    {
        $lead = $this->leadRepository->findOrFail($leadId);

        //validate if the stage exists in the pipeline
        $lead->pipeline->stages()
            ->where('id', $nextPipelineStageId)
            ->firstOrFail();

        // Validate status transition
        try {
            LeadStatusTransitionValidator::validateTransition($lead, (int)$nextPipelineStageId);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Status transitie validatie gefaald',
                'errors' => $e->errors(),
            ], 422);
        }

        Event::dispatch('lead.update.before', $leadId);

        $lead = $this->leadRepository->update(
            [
                'entity_type' => 'leads',
                'lead_pipeline_stage_id' => $nextPipelineStageId,
            ],
            $leadId,
            ['lead_pipeline_stage_id']
        );

        Event::dispatch('lead.update.after', $lead);

        return response()->json([
            'message' => trans('admin::app.leads.update-success'),
        ]);
    }

    /**
     * Search person results.
     */
    public function search(): AnonymousResourceCollection|JsonResponse
    {
        // Normalize legacy search params: map `name` to first/last/married_name, preserve other tokens (e.g. user.name)
        $search = request()->query('search', '');
        $searchFields = request()->query('searchFields', '');

        if ($search && str_contains($search, 'name:')) {
            preg_match('/name:([^;]+);?/i', $search, $m);
            $term = isset($m[1]) ? trim($m[1]) : '';
            if ($term !== '') {
                // Remove name:* from search tokens
                $tokens = array_values(array_filter(array_map('trim', explode(';', $search))));
                $tokens = array_values(array_filter($tokens, fn($t) => !str_starts_with($t, 'name:')));
                // Append expanded fields
                array_push($tokens, 'first_name:' . $term, 'last_name:' . $term, 'married_name:' . $term);
                $newSearch = implode(';', $tokens) . ';';

                // Build searchFields: remove name:like; ensure :like for expanded fields, keep others
                $sfParts = array_values(array_filter(array_map('trim', explode(';', (string) $searchFields))));
                $sfParts = array_values(array_filter($sfParts, fn($p) => !str_starts_with($p, 'name:')));
                $need = ['first_name', 'last_name', 'married_name'];
                $existing = array_map(fn($p) => explode(':', $p)[0] ?? $p, $sfParts);
                foreach ($need as $nf) {
                    if (!in_array($nf, $existing, true)) {
                        $sfParts[] = $nf . ':like';
                    }
                }
                $newSearchFields = implode(';', $sfParts) . ';';

                // Force OR join so tokens are combined permissively
                request()->merge([
                    'search' => $newSearch,
                    'searchFields' => $newSearchFields,
                    'searchJoin' => 'or',
                ]);
            }
        }

        // Normalize user.name (compact UI token) to first_name/last_name
        $this->normalizeUserNameSearch();

        // Normalize convenience tokens for email/phone to underlying JSON columns
        $search = request()->query('search', '');
        if ($search && (str_contains($search, 'email:') || str_contains($search, 'phone:'))) {
            $tokens = array_values(array_filter(array_map('trim', explode(';', $search))));
            $normalized = [];
            foreach ($tokens as $tok) {
                if (str_starts_with($tok, 'email:')) {
                    $term = substr($tok, strlen('email:'));
                    // map to emails column
                    $normalized[] = 'emails:' . $term;
                } elseif (str_starts_with($tok, 'phone:')) {
                    $term = substr($tok, strlen('phone:'));
                    // map to phones column
                    $normalized[] = 'phones:' . $term;
                } else {
                    $normalized[] = $tok;
                }
            }
            request()->merge(['search' => implode(';', $normalized) . ';']);
            // ensure like conditions exist for emails/phones in searchFields if present
            $sf = request()->query('searchFields', '');
            if ($sf) {
                $parts = array_values(array_filter(array_map('trim', explode(';', $sf))));
                $fields = array_map(fn($p) => explode(':', $p)[0] ?? $p, $parts);
                foreach (['emails', 'phones'] as $f) {
                    if (!in_array($f, $fields, true)) {
                        $parts[] = $f . ':like';
                    }
                }
                request()->merge(['searchFields' => implode(';', $parts) . ';']);
            }
            // Always OR join for convenience tokens
            request()->merge(['searchJoin' => 'or']);
        }

        // Validate requested search fields against repository's searchable fields
        if ($resp = $this->validateSearchFieldsAgainstAllowed($this->leadRepository->getFieldsSearchable())) {
            return $resp;
        }

        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $results = $this->leadRepository
                ->pushCriteria(app(RequestCriteria::class))
                ->findWhereIn('user_id', $userIds);
        } else {
            $results = $this->leadRepository
                ->pushCriteria(app(RequestCriteria::class))
                ->all();
        }

        return LeadResource::collection($results);
    }

    /**
     * Return open leads for a given person (exclude won/lost stages).
     */
    public function openByPerson(Person $person): AnonymousResourceCollection
    {
        $results = $this->leadRepository
            ->with(['stage'])
            ->scopeQuery(function($q) use ($person) {
                return $q->whereHas('persons', function($qq) use ($person) {
                        $qq->where('persons.id', $person->id);
                    })
                    ->whereHas('stage', function($qq) {
                        $qq->where('is_won', false)->where('is_lost', false);
                    });
            })
            ->all();

        return LeadResource::collection($results);
    }

    /**
     * Validate requested search fields against repository definitions.
     * Returns JsonResponse(400) on invalid, otherwise null.
     */
    private function validateSearchFieldsAgainstAllowed(array $fieldsSearchable): ?JsonResponse
    {
        $requestedFieldsParam = request()->query('searchFields', '');
        if (empty($requestedFieldsParam)) {
            return null;
        }

        $requestedFields = array_filter(explode(';', $requestedFieldsParam));
        $requestedFieldNames = array_map(function ($f) {
            $parts = explode(':', $f);
            return $parts[0] ?? $f;
        }, $requestedFields);

        $allowed = [];
        foreach ($fieldsSearchable as $key => $value) {
            $allowed[] = is_int($key) ? $value : $key;
        }

        foreach ($requestedFieldNames as $field) {
            if ($field === '') {
                continue;
            }
            if (!in_array($field, $allowed, true)) {
                return response()->json([
                    'message' => 'Invalid search field',
                    'field' => $field,
                ], 400);
            }
        }

        return null;
    }

    /**
     * Normalize `user.name:term` into `user.first_name:term;user.last_name:term` with like semantics.
     */
    private function normalizeUserNameSearch(): void
    {
        $search = request()->query('search', '');
        $searchFields = request()->query('searchFields', '');

        if (!$search || !str_contains($search, 'user.name:')) {
            return;
        }

        // Rewrite search tokens
        $tokens = array_values(array_filter(array_map('trim', explode(';', $search))));
        $rebuilt = [];
        foreach ($tokens as $tok) {
            if (str_starts_with($tok, 'user.name:')) {
                $term = trim(substr($tok, strlen('user.name:')));
                if ($term !== '') {
                    $rebuilt[] = 'user.first_name:' . $term;
                    $rebuilt[] = 'user.last_name:' . $term;
                }
            } else {
                $rebuilt[] = $tok;
            }
        }
        request()->merge(['search' => implode(';', $rebuilt) . ';']);

        // Update searchFields: replace user.name with first_name/last_name like
        if ($searchFields) {
            $sfParts = array_values(array_filter(array_map('trim', explode(';', $searchFields))));
            $sfParts = array_values(array_filter($sfParts, fn($p) => !str_starts_with($p, 'user.name:') && $p !== 'user.name'));
            $existing = array_map(fn($p) => explode(':', $p)[0] ?? $p, $sfParts);
            foreach (['user.first_name', 'user.last_name'] as $nf) {
                if (!in_array($nf, $existing, true)) {
                    $sfParts[] = $nf . ':like';
                }
            }
            request()->merge(['searchFields' => implode(';', $sfParts) . ';']);
        } else {
            request()->merge(['searchFields' => 'user.first_name:like;user.last_name:like;']);
        }

        // Be permissive between tokens
        request()->merge(['searchJoin' => 'or']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->leadRepository->findOrFail($id);

        try {
            Event::dispatch('lead.delete.before', $id);

            $this->leadRepository->delete($id);

            Event::dispatch('lead.delete.after', $id);

            return response()->json([
                'message' => trans('admin::app.leads.destroy-success'),
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.leads.destroy-failed'),
            ], 400);
        }
    }

    /**
     * Mass update the specified resources.
     */
    public function massUpdate(MassUpdateRequest $massUpdateRequest): JsonResponse
    {
        $leads = $this->leadRepository->findWhereIn('id', $massUpdateRequest->input('indices'));

        try {
            foreach ($leads as $lead) {
                Event::dispatch('lead.update.before', $lead->id);

                $lead = $this->leadRepository->find($lead->id);

                $lead?->update(['lead_pipeline_stage_id' => $massUpdateRequest->input('value')]);

                Event::dispatch('lead.update.before', $lead->id);
            }

            return response()->json([
                'message' => trans('admin::app.leads.update-success'),
            ]);
        } catch (\Exception $th) {
            return response()->json([
                'message' => trans('admin::app.leads.update-failed'),
            ], 400);
        }
    }

    /**
     * Mass delete the specified resources.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $leads = $this->leadRepository->findWhereIn('id', $massDestroyRequest->input('indices'));

        try {
            foreach ($leads as $lead) {
                Event::dispatch('lead.delete.before', $lead->id);

                $this->leadRepository->delete($lead->id);

                Event::dispatch('lead.delete.after', $lead->id);
            }

            return response()->json([
                'message' => trans('admin::app.leads.destroy-success'),
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.leads.destroy-failed'),
            ]);
        }
    }


    /**
     * Kanban lookup.
     */
    public function kanbanLookup()
    {
        $params = $this->validate(request(), [
            'column' => ['required'],
            'search' => ['required', 'min:2'],
        ]);

        /**
         * Finding the first column from the collection.
         */
        $column = collect($this->getKanbanColumns())->where('index', $params['column'])->firstOrFail();

        /**
         * Fetching on the basis of column options.
         */
        return app($column['filterable_options']['repository'])
            ->select([$column['filterable_options']['column']['label'] . ' as label', $column['filterable_options']['column']['value'] . ' as value'])
            ->where($column['filterable_options']['column']['label'], 'LIKE', '%' . $params['search'] . '%')
            ->get()
            ->map
            ->only('label', 'value');
    }

    /**
     * Get columns for the kanban view.
     */
    private function getKanbanColumns(): array
    {
        return [
            [
                'index' => 'id',
                'label' => trans('admin::app.leads.index.kanban.columns.id'),
                'type' => 'integer',
                'searchable' => false,
                'search_field' => 'in',
                'filterable' => true,
                'filterable_type' => null,
                'filterable_options' => [],
                'allow_multiple_values' => true,
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'index' => 'user_id',
                'label' => trans('admin::app.leads.index.kanban.columns.sales-person'),
                'type' => 'string',
                'searchable' => false,
                'search_field' => 'in',
                'filterable' => true,
                'filterable_type' => 'searchable_dropdown',
                'filterable_options' => [
                    'repository' => UserRepository::class,
                    'column' => [
                        'label' => DB::raw("CONCAT(users.first_name, ' ', users.last_name)"),
                        'value' => 'id',
                    ],
                ],
                'allow_multiple_values' => true,
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'index' => 'persons.id',
                'label' => trans('admin::app.leads.index.kanban.columns.contact-person'),
                'type' => 'string',
                'searchable' => false,
                'search_field' => 'in',
                'filterable' => true,
                'filterable_options' => [],
                'allow_multiple_values' => true,
                'sortable' => true,
                'visibility' => true,
                'filterable_type' => 'searchable_dropdown',
                'filterable_options' => [
                    'repository' => PersonRepository::class,
                    'column' => [
                        'label' => DB::raw("CONCAT_WS(' ', persons.first_name, persons.lastname_prefix, persons.last_name)"),
                        'value' => 'id',
                    ],
                ],
            ],
            [
                'index' => 'lead_type_id',
                'label' => trans('admin::app.leads.index.kanban.columns.lead-type'),
                'type' => 'string',
                'searchable' => false,
                'search_field' => 'in',
                'filterable' => true,
                'filterable_type' => 'dropdown',
                'filterable_options' => $this->typeRepository->all(['name as label', 'id as value'])->toArray(),
                'allow_multiple_values' => true,
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'index' => 'lead_source_id',
                'label' => trans('admin::app.leads.index.kanban.columns.source'),
                'type' => 'string',
                'searchable' => false,
                'search_field' => 'in',
                'filterable' => true,
                'filterable_type' => 'dropdown',
                'filterable_options' => $this->sourceRepository->all(['name as label', 'id as value'])->toArray(),
                'allow_multiple_values' => true,
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'index' => 'tags.name',
                'label' => trans('admin::app.leads.index.kanban.columns.tags'),
                'type' => 'string',
                'searchable' => false,
                'search_field' => 'in',
                'filterable' => true,
                'filterable_options' => [],
                'allow_multiple_values' => true,
                'sortable' => true,
                'visibility' => true,
                'filterable_type' => 'searchable_dropdown',
                'filterable_options' => [
                    'repository' => TagRepository::class,
                    'column' => [
                        'label' => 'name',
                        'value' => 'name',
                    ],
                ],
            ],
        ];
    }

    /**
     * Create lead with specified AI.
     */
    public function createByAI()
    {
        $leadData = [];

        $errorMessages = [];

        foreach (request()->file('files') as $file) {
            $lead = $this->processFile($file);

            if (
                isset($lead['status'])
                && $lead['status'] === 'error'
            ) {
                $errorMessages[] = $lead['message'];
            } else {
                $leadData[] = $lead;
            }
        }

        if (isset($errorMessages[0]['code'])) {
            return response()->json(MagicAI::errorHandler($errorMessages[0]['message']));
        }

        if (
            empty($leadData)
            && !empty($errorMessages)
        ) {
            return response()->json(MagicAI::errorHandler(implode(', ', $errorMessages)), 400);
        }

        if (empty($leadData)) {
            return response()->json(MagicAI::errorHandler(trans('admin::app.leads.no-valid-files')), 400);
        }

        return response()->json([
            'message' => trans('admin::app.leads.create-success'),
            'leads' => $this->createLeads($leadData),
        ]);
    }

    /**
     * Process file.
     *
     * @param mixed $file
     */
    private function processFile($file)
    {
        $validator = Validator::make(
            ['file' => $file],
            ['file' => 'required|extensions:' . str_replace(' ', '', self::SUPPORTED_TYPES)]
        );

        if ($validator->fails()) {
            return MagicAI::errorHandler($validator->errors()->first());
        }

        $base64Pdf = base64_encode(file_get_contents($file->getRealPath()));

        $extractedData = MagicAIService::extractDataFromFile($base64Pdf);

        $lead = MagicAI::mapAIDataToLead($extractedData);

        return $lead;
    }

    /**
     * Create multiple leads.
     */
    private function createLeads($rawLeads): array
    {
        $leads = [];

        foreach ($rawLeads as $rawLead) {
            Event::dispatch('lead.create.before');

            foreach ($rawLead['person']['emails'] as $email) {
                $person = $this->personRepository
                    ->whereJsonContains('emails', [['value' => $email['value']]])
                    ->first();

                if ($person) {
                    $rawLead['person']['id'] = $person->id;

                    break;
                }
            }

            $pipeline = $this->pipelineRepository->getDefaultPipeline();

            $stage = $pipeline->stages()->first();

            $lead = $this->leadRepository->create(array_merge($rawLead, [
                'lead_pipeline_id' => $pipeline->id,
                'lead_pipeline_stage_id' => $stage->id,
            ]));

            Event::dispatch('lead.create.after', $lead);

            $leads[] = $lead;
        }

        return $leads;
    }

    /**
     * Normalize contact arrays to ensure proper data types
     * @deprecated Use normalizeContactFields() from NormalizesContactFields trait instead
     */
    private function normalizeContactArrays($request)
    {
        // Replaced by normalizeContactFields() from trait
        $this->normalizeContactFields($request);
    }

    /**
     * Detach person from lead.
     */
    public function detachPerson(int $leadId, int $personId)
    {
        try {
            // Remove the relationship
            DB::table('lead_persons')
                ->where('lead_id', $leadId)
                ->where('person_id', $personId)
                ->delete();

            // Delete anamnesis linked to this lead-person combination
            Anamnesis::where('lead_id', $leadId)
                ->where('person_id', $personId)
                ->delete();

            return response()->json([
                'message' => 'Persoon succesvol ontkoppeld van lead.',
                'success' => true
            ]);

        } catch (Exception $e) {
            Log::error('Error detaching person from lead', [
                'lead_id' => $leadId,
                'person_id' => $personId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Er is een fout opgetreden bij het ontkoppelen van de persoon.',
                'success' => false
            ], 500);
        }
    }

    /**
     * Determine the appropriate pipeline and stage for a lead based on request parameters
     *
     * @param int|null $pipelineId Pipeline ID from request
     * @param int|null $stageId Stage ID from request
     * @param int $defaultDepartmentId Default department ID based on user groups
     * @return array Array containing pipeline, stage_id, and department_id
     */
    private function determinePipelineForLead(?int $pipelineId, ?int $stageId, int $defaultDepartmentId): array
    {
        $pipeline = null;
        $defaultStageId = null;

        // First check if pipeline_id is provided in request
        if ($pipelineId) {
            $pipeline = $this->pipelineRepository->find($pipelineId);
        }

        // If stage_id is provided, get pipeline from stage
        if (!$pipeline && $stageId) {
            $stage = $this->stageRepository->find($stageId);
            if ($stage) {
                $pipeline = $stage->pipeline;
                $defaultStageId = $stage->id;
            }
        }

        // If no pipeline found yet, determine based on user department
        if (!$pipeline) {
            // Map department to pipeline
            if ($defaultDepartmentId == Department::findHerniaId()) {
                $pipeline = $this->pipelineRepository->find(PipelineDefaultKeys::PIPELINE_HERNIA_ID->value);
            } else {
                $pipeline = $this->pipelineRepository->find(PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value);
            }
        }

        // If still no pipeline, fall back to default
        if (!$pipeline) {
            $pipeline = $this->pipelineRepository->getDefaultPipeline();
        }

        // Get first stage if no specific stage was requested
        if (!$defaultStageId && $pipeline) {
            $defaultStageId = $pipeline->stages()->first()->id ?? null;
        }

        // Override department_id based on the determined pipeline
        if ($pipeline) {
            if ($pipeline->id == PipelineDefaultKeys::PIPELINE_HERNIA_ID->value) {
                $defaultDepartmentId = Department::findHerniaId();
            } elseif ($pipeline->id == PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value) {
                $defaultDepartmentId = Department::findPrivateScanId();
            }
        }

        return [
            'pipeline' => $pipeline,
            'stage_id' => $defaultStageId,
            'department_id' => $defaultDepartmentId
        ];
    }

    /**
     * Mark lead as lost and complete all open activities
     */
    public function markAsLost(int $id): JsonResponse
    {
        $this->validate(request(), [
            'lost_reason' => ['required', new Enum(LostReason::class)],
            'closed_at' => 'nullable|date',
        ]);

        $lead = $this->leadRepository->findOrFail($id);

        // Find the lost stage for this lead's pipeline
        $lostStage = $lead->pipeline->stages()
            ->where('code', 'like', 'lost%')
            ->first();

        if (!$lostStage) {
            return response()->json([
                'message' => 'Geen "Verloren" status gevonden voor deze pipeline.',
            ], 422);
        }

        // Update lead to lost status
        $leadData = [
            'lead_pipeline_stage_id' => $lostStage->id,
            'lost_reason' => request('lost_reason'),
            'closed_at' => request('closed_at') ?: now(),
        ];

        $lead->update($leadData);

        // Complete all open activities for this lead
        $this->completeAllOpenActivitiesForLead($lead->id);

        return response()->json([
            'message' => 'Lead succesvol afgevoerd en alle opstaande activiteiten zijn afgerond.',
        ]);
    }

    /**
     * Complete all open activities for a lead
     */
    private function completeAllOpenActivitiesForLead(int $leadId): void
    {
        $activityRepository = app(ActivityRepository::class);

        $openActivities = $activityRepository
            ->where('lead_id', $leadId)
            ->where('is_done', 0)
            ->get();

        foreach ($openActivities as $activity) {
            $activityRepository->update([
                'is_done' => 1,
                'status' => ActivityStatus::DONE->value,
            ], $activity->id);
        }
    }

    /**
     * Check if lead should redirect to sync page based on conditions:
     * - Lead has exactly 1 person
     * - New match score is not 100
     */
    private function shouldRedirectToSync($lead): bool
    {
        try {
            // Check if lead has exactly 1 person using the relationship
            if ($lead->persons()->count() !== 1) {
                return false;
            }

            $person = $lead->persons()->first();

            // Use PersonController to calculate new match score (1-way)
            $personController = app(PersonController::class);
            $matchScore = $personController->calculateMatchScore($lead, $person);

            // Return true if match score is not 100 (perfect match)
            return $matchScore < 100;

        } catch (Exception $e) {
            // Log the error and return false to prevent sync redirect
            Log::error('Error in shouldRedirectToSync: ' . $e->getMessage(), [
                'lead_id' => $lead->id,
                'exception' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Normalize phone number for comparison.
     * This is a copy of the method from PersonController to avoid circular dependencies.
     */
    private function normalizePhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $normalized = preg_replace('/[^0-9]/', '', $phone);

        // Handle Dutch phone numbers - convert +31 to 0
        if (str_starts_with($normalized, '31') && strlen($normalized) >= 10) {
            $normalized = '0' . substr($normalized, 2);
        }

        return $normalized;
    }

    /**
     * Format date for comparison, handling invalid dates.
     * This is a copy of the method from PersonController to avoid circular dependencies.
     */
    private function formatDateForComparison($date): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            // Check if it's a valid Carbon instance
            if ($date instanceof \Carbon\Carbon) {
                // Check for invalid dates (like -0001-11-30 or 0000-00-00)
                if ($date->year <= 0 || $date->year > 2100) {
                    return null;
                }
                return $date->format('Y-m-d');
            }

            // If it's a string, try to parse it
            if (is_string($date)) {
                // Skip obviously invalid dates
                if (in_array($date, ['0000-00-00', '0000-00-00 00:00:00']) || strpos($date, '-0001') === 0) {
                    return null;
                }

                $carbonDate = \Carbon\Carbon::parse($date);
                if ($carbonDate->year <= 0 || $carbonDate->year > 2100) {
                    return null;
                }
                return $carbonDate->format('Y-m-d');
            }
        } catch (Exception $e) {
            logger()->error('Error parsing date for comparison', [
                'date' => $date,
                'error' => $e->getMessage()
            ]);
            // If parsing fails, treat as null
            return null;
        }

        return null;
    }
}
