<?php

namespace Webkul\Admin\Http\Controllers\Lead;

use App\Enums\ContactLabel;
use App\Enums\LostReason;
use App\Enums\ActivityStatus;
use App\Enums\PipelineDefaultKeys;
use App\Http\Controllers\Concerns\NormalizesContactFields;
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
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\LeadForm;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Requests\MassUpdateRequest;
use Webkul\Admin\Http\Resources\LeadResource;
use Webkul\Admin\Http\Resources\StageResource;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Helpers\MagicAI;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Lead\Repositories\ProductRepository;
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
        protected ProductRepository   $productRepository,
        protected PersonRepository    $personRepository,
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
            $stages = $pipeline->stages->where('id', request()->query('pipeline_stage_id'));
        } else {
            $stages = $pipeline->stages;

            // Filter out won/lost stages if requested to improve performance
            if ($excludeWonLost) {
                $stages = $stages->filter(function ($stage) {
                    return !in_array($stage->code, self::WON_LOST_STAGE_CODES);
                });
            }
        }

        foreach ($stages as $stage) {
            /**
             * We have to create a new instance of the lead repository every time, which is
             * why we're not using the injected one.
             */
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

            $query = app(LeadRepository::class)
                ->pushCriteria(app(RequestCriteria::class))
                ->where([
                    'lead_pipeline_id' => $pipeline->id,
                    'lead_pipeline_stage_id' => $stage->id,
                ]);

            if ($userIds = bouncer()->getAuthorizedUserIds()) {
                $query->whereIn('leads.user_id', $userIds);
            }

            $data[$stage->sort_order] = (new StageResource($stage))->jsonSerialize();

            // Load relationships - including persons for kanban display
            $data[$stage->sort_order]['leads'] = [
                'data' => LeadResource::collection($paginator = $query->with([
                    'tags',
                    'type',
                    'source',
                    'user',
                    'organization',
                    'pipeline',
                    'pipeline.stages',
                    'stage',
                    'attribute_values',
                ])->paginate(10)),

                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'from' => $paginator->firstItem(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ],
            ];
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
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LeadForm $request): RedirectResponse|JsonResponse
    {
        // Normalize contact arrays before validation
        $this->normalizeContactArrays($request);

        // Validate with custom rules including email/phone requirement
        $this->validate($request, LeadValidationService::getWebValidationRules($request));

        // Additional rule now embedded in LeadValidationService rules

            try {
                [$lead, $leadPipelineId] = $this->storeLead($request);

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

            // Normaliseer is_default naar boolean voor phones
            if (isset($data['phones']) && is_array($data['phones'])) {
                $data['phones'] = array_map(function($phone) {
                    if (isset($phone['is_default'])) {
                        $phone['is_default'] = $phone['is_default'] === true || $phone['is_default'] === 'on' || $phone['is_default'] === '1';
                    }
                    return $phone;
                }, $data['phones']);
            }
            // Normaliseer is_default naar boolean voor emails
            if (isset($data['emails']) && is_array($data['emails'])) {
                $data['emails'] = array_map(function($email) {
                    if (isset($email['is_default'])) {
                        $email['is_default'] = $email['is_default'] === true || $email['is_default'] === 'on' || $email['is_default'] === '1';
                    }
                    return $email;
                }, $data['emails']);
            }

            $data['status'] = 1;

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

            if (in_array($stage->code, ['won', 'lost'])) {
                $data['closed_at'] = Carbon::now();
            }
            $lead = $this->leadRepository->create($data);

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
        $lead = $this->leadRepository->with(['address', 'organization'])->findOrFail($id);

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
        try {
            try {
                $this->validate($request, LeadValidationService::getWebValidationRules($request, false));
            } catch (ValidationException $exception) {
                // for missing error displaying in the UI
                logger()->warning('Validation error during lead update', ['errors' => $exception->errors()]);
                throw $exception;
            }
            // Additional rule now embedded in LeadValidationService rules
            Event::dispatch('lead.update.before', $id);

            $data = $request->all();
            // Handle empty date field
            if (array_key_exists('date_of_birth', $data) && ($data['date_of_birth'] === '' || $data['date_of_birth'] === null)) {
                $data['date_of_birth'] = null;
            }

            // Normaliseer is_default naar boolean voor phones
            if (isset($data['phones']) && is_array($data['phones'])) {
                $data['phones'] = array_map(function($phone) {
                    if (isset($phone['is_default'])) {
                        $phone['is_default'] = $phone['is_default'] === true || $phone['is_default'] === 'on' || $phone['is_default'] === '1';
                    }
                    return $phone;
                }, $data['phones']);
            }
            // Normaliseer is_default naar boolean voor emails
            if (isset($data['emails']) && is_array($data['emails'])) {
                $data['emails'] = array_map(function($email) {
                    if (isset($email['is_default'])) {
                        $email['is_default'] = $email['is_default'] === true || $email['is_default'] === 'on' || $email['is_default'] === '1';
                    }
                    return $email;
                }, $data['emails']);
            }

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

            Event::dispatch('lead.update.after', $lead);

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
     * Update the lead attributes.
     */
    public function updateAttributes(int $id)
    {
        $data = request()->all();

        $attributes = $this->attributeRepository->findWhere([
            'entity_type' => 'leads',
            ['code', 'NOTIN', ['description']],
        ]);

        Event::dispatch('lead.update.before', $id);

        $lead = $this->leadRepository->update($data, $id, $attributes);

        Event::dispatch('lead.update.after', $lead);

        return response()->json([
            'message' => trans('admin::app.leads.update-success'),
        ]);
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
                    'message' => 'Status transitie validatie gefaald',
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
            } catch (\Exception $e) {
                // Log but don't fail the stage update response
                \Log::warning('Failed to complete open activities during stage update', [
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
    public function openByPerson(\Webkul\Contact\Models\Person $person): AnonymousResourceCollection
    {
        $results = $this->leadRepository
            ->with(['stage'])
            ->scopeQuery(function($q) use ($person) {
                return $q->whereHas('persons', function($qq) use ($person) {
                        $qq->where('persons.id', $person->id);
                    })
                    ->whereHas('stage', function($qq) {
                        $qq->whereNotIn('code', self::WON_LOST_STAGE_CODES);
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
     * Attach product to lead.
     */
    public function addProduct(int $leadId): JsonResponse
    {
        $product = $this->productRepository->updateOrCreate(
            [
                'lead_id' => $leadId,
                'product_id' => request()->input('product_id'),
            ],
            array_merge(
                request()->all(),
                [
                    'lead_id' => $leadId,
                    'amount' => request()->input('price') * request()->input('quantity'),
                ],
            )
        );

        return response()->json([
            'data' => $product,
            'message' => trans('admin::app.leads.update-success'),
        ]);
    }

    /**
     * Remove product attached to lead.
     */
    public function removeProduct(int $id): JsonResponse
    {
        try {
            Event::dispatch('lead.product.delete.before', $id);

            $this->productRepository->deleteWhere([
                'lead_id' => $id,
                'product_id' => request()->input('product_id'),
            ]);

            Event::dispatch('lead.product.delete.after', $id);

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
                        'label' => 'name',
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
                        'label' => 'name',
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

        try {
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

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Er is een fout opgetreden bij het afvoeren van de lead: ' . $e->getMessage(),
            ], 500);
        }
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
}
