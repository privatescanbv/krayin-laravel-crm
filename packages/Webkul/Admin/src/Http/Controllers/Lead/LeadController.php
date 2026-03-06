<?php

namespace Webkul\Admin\Http\Controllers\Lead;

use App\Enums\LostReason;
use App\Enums\ActivityStatus;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineType;
use App\Http\Controllers\Concerns\NormalizesContactFields;
use App\Http\Controllers\Concerns\HandlesReturnUrl;
use BackedEnum;
use Webkul\Admin\Http\Controllers\Concerns\HasAdvancedSearch;
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
use Webkul\Admin\Http\Resources\LeadLookupResource;
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
use Webkul\User\Repositories\UserRepository;
use InvalidArgumentException;
use App\Services\LeadValidationService;
use App\Services\PipelineCookieService;
use App\Services\LeadStatusTransitionValidator;
use App\Repositories\AddressRepository;
use App\Services\FormService;
use App\Services\UserDefaultValueService;

class LeadController extends Controller
{
    use NormalizesContactFields, HasAdvancedSearch, HandlesReturnUrl;

    /**
     * Const variable for supported types.
     */
    const SUPPORTED_TYPES = 'pdf,bmp,jpeg,jpg,png,webp';

    /**
     * Const variable for won/lost stage codes.
     */
    const WON_LOST_STAGE_CODES = ['won', 'lost', 'won-hernia', 'lost-hernia'];


    private bool $enableDebugLogging = false;

    /**
     * Get search configuration for leads.
     */
    protected function getSearchConfig(): array
    {
        return [
            'name_fields' => ['first_name', 'last_name', 'married_name'],
            'supports_email_phone_search' => true,
            'supports_user_name_search' => false,
            'enable_debug_logging' => $this->enableDebugLogging,
            'table_name' => 'leads',
        ];
    }

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
        protected UserDefaultValueService $userDefaultValueService,
        private readonly ActivityRepository $activityRepository
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
        $pipeline = $this->pipelineCookieService->getPipeline(PipelineType::LEAD, request('pipeline_id'));
        $stages = $pipeline->stages->map(function ($stage) {
            return [
                'id'          => $stage->id,
                'name'        => $stage->name,
                'description' => $stage->description,
                'sort_order'  => $stage->sort_order,
                'leads'       => [
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
        if(is_null($pipeline)) {
            throw new Exception('No pipeline found for leads index page');
        }
        return view('admin::leads.index', [
            'pipeline' => $pipeline,
            'stages' => $stages,
        ]);
    }

    /**
     * Returns a listing of the resource.
     */
    public function get(): JsonResponse
    {
        try {
        // Get effective pipeline ID (URL parameter takes precedence over cookie)
        $pipeline = $this->pipelineCookieService
            ->getPipeline(PipelineType::LEAD, request()->query('pipeline_id'));
        /** @var \Webkul\Lead\Models\Pipeline $pipeline */

        // Check if we should exclude won/lost stages for performance optimization
        $excludeWonLost = filter_var(request()->query('exclude_won_lost', false), FILTER_VALIDATE_BOOLEAN);

        if (request()->query('pipeline_stage_id')) {
            $stages = $pipeline->stages()->select('id','code','name','description','sort_order','lead_pipeline_id','is_won','is_lost')->where('id', request()->query('pipeline_stage_id'))->get();
        } else {
            $stages = $pipeline->stages()->select('id','code','name','description','sort_order','lead_pipeline_id','is_won','is_lost')->get();

            // Filter out won/lost stages if requested to improve performance
            if ($excludeWonLost) {
                $stages = $stages->filter(function ($stage) {
                    return !($stage->is_won || $stage->is_lost);
                });
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

        // Create pipeline model once for manual relation setting (avoids N+1 in getRottenDaysAttribute)
        $pipelineModel = new Pipeline([
            'id' => $pipeline->id,
            'rotten_days' => $pipeline->rotten_days,
        ]);
        $perPage = (int) request()->query('limit', 10);

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
                // Use simplePaginate to skip the redundant COUNT(*) query per stage.
                // The total is already known from $totalsByStage above.
                $paginator = $query->select([
                    'leads.id',
                    'leads.first_name',
                    'leads.last_name',
                    'leads.married_name',
                    'leads.lastname_prefix',
                    'leads.salutation',
                    'leads.gender',
                    'leads.date_of_birth',
                    'leads.created_at',
                    'leads.married_name_prefix',
                    'leads.lead_pipeline_id',
                    'leads.lead_pipeline_stage_id',
                    'leads.user_id',
                    'leads.mri_status',
                    'leads.lost_reason',
                    'leads.diagnosis_form_id',
                    DB::raw('COALESCE(open_activities.open_activity_count, 0) AS open_activities_count_query'),
                    DB::raw('COALESCE(open_emails.open_email_count, 0) AS open_email_count_query'),
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
                ->orderBy(
                    'leads.created_at',
                    in_array(request('order'), ['asc', 'desc'], true) ? request('order') : 'desc'
                )
                ->paginate($perPage, ['*'], 'page');

                // Set pipeline and stage relations manually to prevent N+1 queries
                foreach ($paginator->items() as $lead) {
                    $lead->setRelation('pipeline', $pipelineModel);
                    $lead->setRelation('stage', $stage);
                }

                $lastPage = (int) ceil($totalForStage / max($perPage, 1));

                $data[$stage->id]['leads'] = [
                    'data' => LeadKanbanResource::collection($paginator),

                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'from' => $paginator->firstItem(),
                        'last_page' => $lastPage,
                        'per_page' => $paginator->perPage(),
                        'to' => $paginator->lastItem(),
                        'total' => $totalForStage,
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
        $effectivePipelineId = $this->pipelineCookieService
            ->getPipeline(PipelineType::LEAD, request('pipeline_id'))
            ->id;
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
                        'gender' => $person->gender,
                        'salutation' => $person->salutation,
                        'national_identification_number' => $person->national_identification_number,
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
                        'national_identification_number' => $person->national_identification_number,
                        'emails' => is_array($person->emails) ? $person->emails : [],
                        'phones' => is_array($person->phones) ? $person->phones : [],
                    ];
                }
            } catch (Exception $e) {
                // Ignore if person not found
            }
        }

        // Handle query parameters for email, first_name, last_name, phone
        $queryEmail = request('email');
        $queryFirstName = request('first_name');
        $queryLastName = request('last_name');
        $queryPhone = request('phone');

        if ($queryEmail || $queryFirstName || $queryLastName || $queryPhone) {
            // If no person is prefilled, create a prefilled lead person from query params
            if (!$prefilledLeadPerson) {
                $prefilledLeadPerson = [];
            }

            // Parse email - handle both string and JSON array formats
            $emailValue = null;
            if ($queryEmail) {
                // Try to decode if it's JSON
                $decoded = json_decode($queryEmail, true);
                if (is_array($decoded) && !empty($decoded)) {
                    $emailValue = is_string($decoded[0]) ? $decoded[0] : (is_array($decoded[0]) ? ($decoded[0]['value'] ?? $decoded[0]['email'] ?? null) : null);
                } else {
                    $emailValue = $queryEmail;
                }
            }

            // Set email in proper format
            if ($emailValue) {
                $prefilledLeadPerson['emails'] = [['value' => $emailValue, 'label' => 'primary']];
            }

            // Set first and last name if provided
            if ($queryFirstName && empty($prefilledLeadPerson['first_name'])) {
                $prefilledLeadPerson['first_name'] = $queryFirstName;
            }
            if ($queryLastName && empty($prefilledLeadPerson['last_name'])) {
                $prefilledLeadPerson['last_name'] = $queryLastName;
            }

            // Set phone if provided
            if ($queryPhone) {
                $prefilledLeadPerson['phones'] = [['value' => $queryPhone, 'label' => 'primary']];
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
            'linkEmailId' => request('email_id'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LeadForm $request): RedirectResponse|JsonResponse
    {
        try {
            $this->normalizeContactFields($request);
            $this->validateLeadRequest($request, true);

            return DB::transaction(function () use ($request) {
                /**
                 * Create flow helper: optionally create a new person from the entered lead data
                 * and link it to the lead on first save.
                 *
                 * This mirrors the manual "Contact aanmaken" action from the lead edit flow
                 * (multi-contactmatcher), but runs server-side during lead creation.
                 */
                if ($request->boolean('create_person_from_lead')) {
                    $existingPersonIds = array_values(array_filter((array) $request->input('person_ids', [])));

                    // Only auto-create when no person has been selected yet (avoid accidental duplicates).
                    if (count($existingPersonIds) === 0) {
                        $personPayload = $this->buildPersonPayloadFromLeadRequest($request);
                        /** @var \Webkul\Contact\Models\Person $person */
                        $person = $this->personRepository->create($personPayload);

                        // Link this person to the lead on create (lead->persons relation).
                        $request->merge(['person_ids' => [$person->id]]);
                    }
                }

                [$lead] = $this->storeLead($request);

                // Link the originating email to this lead if email_id was passed
                if ($linkEmailId = $request->input('link_email_id')) {
                    DB::table('emails')
                        ->where('id', (int) $linkEmailId)
                        ->whereNull('lead_id')
                        ->update(['lead_id' => $lead->id]);
                }

                // Check if we should redirect to sync page after creation
                if ($this->shouldRedirectToSync($lead)) {
                    $person = $lead->persons()->first();

                    return $this->respondSuccess(
                        message: trans('admin::app.leads.create-success'),
                        redirectRoute: 'admin.leads.sync-lead-to-person',
                        redirectParams: ['leadId' => $lead->id, 'personId' => $person->id],
                    );
                }

                // Na aanmaken: ga naar de lead detailpagina in plaats van het kanban bord
                return $this->respondSuccess(
                    message: trans('admin::app.leads.create-success'),
                    redirectRoute: 'admin.leads.view',
                    redirectParams: [$lead->id],
                );
            });
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
     * Build a person payload from the current lead create request.
     * Keeps field selection and address rules aligned with the lead edit "Contact aanmaken" action.
     *
     * @throws \InvalidArgumentException when insufficient data is present to create a person.
     */
    private function buildPersonPayloadFromLeadRequest(LeadForm $request): array
    {
        $firstName = trim((string) $request->input('first_name', ''));
        $lastName = trim((string) $request->input('last_name', ''));

        $emails = $request->input('emails', []);
        $phones = $request->input('phones', []);

        $emails = is_array($emails) ? $emails : [];
        $phones = is_array($phones) ? $phones : [];

        $hasName = ($firstName !== '' || $lastName !== '');
        $hasEmail = collect($emails)->contains(function ($e) {
            return is_array($e) && trim((string) ($e['value'] ?? '')) !== '';
        });

        if (! $hasName && ! $hasEmail) {
            throw new InvalidArgumentException('Kan geen persoon aanmaken: naam of e-mail is vereist.');
        }

        $payload = [
            'entity_type' => 'persons',
            // Persons created from the lead create flow should be active by default.
            // (Otherwise they show up as "[Inactief]" in the UI immediately after creation.)
            'is_active' => true,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'lastname_prefix' => (string) $request->input('lastname_prefix', ''),
            'married_name' => (string) $request->input('married_name', ''),
            'married_name_prefix' => (string) $request->input('married_name_prefix', ''),
            'initials' => (string) $request->input('initials', ''),
            'gender' => (string) $request->input('gender', ''),
            'salutation' => (string) $request->input('salutation', ''),
            'national_identification_number' => (string) $request->input('national_identification_number', ''),
            'emails' => $emails,
            'phones' => $phones,
        ];

        $dob = trim((string) $request->input('date_of_birth', ''));
        if ($dob !== '') {
            $payload['date_of_birth'] = $dob;
        } else {
            $payload['date_of_birth'] = null;
        }

        $addressPayload = $this->buildPersonAddressPayloadFromLeadRequest($request);
        if ($addressPayload !== null) {
            $payload['address'] = $addressPayload;
        }

        return $payload;
    }

    /**
     * Address rules match the edit-flow "Contact aanmaken" action:
     * only include address when at least house_number + postal_code are present.
     */
    private function buildPersonAddressPayloadFromLeadRequest(LeadForm $request): ?array
    {
        $address = $request->input('address', []);
        if (! is_array($address)) {
            return null;
        }

        $toString = static fn ($val) => ($val === null || $val === false) ? '' : trim((string) $val);

        $payload = [
            'street' => $toString($address['street'] ?? ''),
            'house_number' => $toString($address['house_number'] ?? ''),
            'house_number_suffix' => $toString($address['house_number_suffix'] ?? ''),
            'postal_code' => $toString($address['postal_code'] ?? ''),
            'city' => $toString($address['city'] ?? ''),
            'state' => $toString($address['state'] ?? ''),
            'country' => $toString($address['country'] ?? ''),
        ];

        // Address creation requires at least house number and postal code.
        if ($payload['house_number'] === '' || $payload['postal_code'] === '') {
            return null;
        }

        return $payload;
    }

    /**
     * @param LeadForm $request
     * @throws InvalidArgumentException validation
     * @return array
     */
    public function storeLead(LeadForm $request): array
    {
            Event::dispatch('lead.create.before');

            $data = $this->prepareLeadDataForUpsert($request, true);

            // Contact normalization is now handled by normalizeContactFields()
            // Used by Krayin to process AI generated leads by file
            $data['status'] = 0;

            $stage = $this->applyPipelineAndStageSelection($data, departmentDeterminesPipeline: false);

            if ($stage->is_won || $stage->is_lost) {
                $data['closed_at'] = Carbon::now();
            }
            $lead = $this->leadRepository->create($data);
            /** @var \Webkul\Lead\Models\Lead $lead */

            // Validate and persist address if provided on lead create
            try {
                if (isset($data['address']) && is_array($data['address'])) {
                    $this->addressRepository->upsertForEntity($lead, $data['address']);
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
            'contactPerson',
            'source',
            'type',
            'channel',
            'department',
            'user',
            'pipeline',
            'stage',
        ])->findOrFail($id);

        $userIds = bouncer()->getAuthorizedUserIds();

        if (
            $userIds
            && !in_array($lead->user_id, $userIds)
        ) {
            // Get last selected pipeline from cookie to preserve selection
            $lastPipelineId = $this->pipelineCookieService->getPipeline(PipelineType::LEAD, null)->id;
            $routeParams = $lastPipelineId ? ['pipeline_id' => $lastPipelineId] : [];
            return redirect()->route('admin.leads.index', $routeParams);
        }
        $activitiesCount = $this->activityRepository->countOpen($lead)->getData()->data;

        return view('admin::leads.view', [
            'lead' => $lead,
            'activitiesCount' => $activitiesCount,
            'canEditLead'     => bouncer()->hasPermission('leads.edit'),
            'canDeleteLead'   => bouncer()->hasPermission('leads.delete'),
            'persons'         => $lead->persons()->get(),
            'contactPerson'   => $lead->contactPerson,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(LeadForm $request, int $id): RedirectResponse|JsonResponse
    {
        $this->normalizeContactFields($request);

        try {
            $this->validateLeadRequest($request, false);
            Event::dispatch('lead.update.before', $id);

            $data = $this->prepareLeadDataForUpsert($request, false);

            // Requirement: on update, department determines the correct pipeline (and a compatible stage).
            if (array_key_exists('department_id', $data) && $data['department_id'] !== null && $data['department_id'] !== '') {
                $this->applyPipelineAndStageSelection($data, departmentDeterminesPipeline: true);
            } elseif (array_key_exists('lead_pipeline_stage_id', $data)) {
                // Backwards compatible: when stage is provided, infer pipeline from that stage.
                $this->applyPipelineAndStageSelection($data, departmentDeterminesPipeline: false);
            }

            $lead = $this->leadRepository->update($data, $id);
            /** @var \Webkul\Lead\Models\Lead $lead */

            // Validate and persist address if provided on lead update
            try {
                if (isset($data['address']) && is_array($data['address'])) {
                    $this->addressRepository->upsertForEntity($lead, $data['address']);
                }
            } catch (Exception $e) {
                throw new InvalidArgumentException($e->getMessage());
            }

            Event::dispatch('lead.update.after', $lead);

            // Check if we should redirect to sync page
            $shouldSync = $this->shouldRedirectToSync($lead);
            if ($shouldSync) {
                $person = $lead->persons()->first();

                return $this->respondSuccess(
                    message: trans('admin::app.leads.update-success'),
                    redirectRoute: 'admin.leads.sync-lead-to-person',
                    redirectParams: ['leadId' => $lead->id, 'personId' => $person->id],
                );
            }

            // If user clicked "Toepassen": stay on edit page; otherwise go to lead view page.
            $redirectRoute = request()->input('submit_action') === 'apply'
                ? 'admin.leads.edit'
                : 'admin.leads.view';

            return $this->respondSuccess(
                message: trans('admin::app.leads.update-success'),
                redirectRoute: $redirectRoute,
                redirectParams: [$lead->id],
            );
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
        logger()->debug('update Stage for lead '.$leadId, ['request ' => request()->all()]);
        $this->validate(request(), [
            'lead_pipeline_stage_id' => 'required|exists:lead_pipeline_stages,id',
            'lost_reason' => ['nullable', new Enum(LostReason::class)],
            'closed_at' => 'nullable|date',
            'user_id' => 'nullable|exists:users,id',
        ]);
        $closeOpenActivities = request()->has('close_open_activities') && request()->input('close_open_activities');

        return $this->updateStageWithData(
            $leadId,
            request()->input('lead_pipeline_stage_id'),
            $closeOpenActivities,
            request()->input('lost_reason'),
            request()->input('closed_at'),
            request()->input('user_id'));
    }

    /**
     * Update the lead stage with additional data.
     */
    public function updateStageWithData(
        int $leadId,
        string $leadPipelineStageId,
        bool $closeOpenActivities,
        ?string $lostReason,
        ?string $closedAt,
        ?string $userId = null
    ): JsonResponse
    {
        $lead = $this->leadRepository->findOrFail($leadId);

        // Apply incoming data to lead model before validation so transition
        // rules (e.g. required_fields) can see the values from the request.
        if (!is_null($userId)) {
            $lead->user_id = $userId;
        }

        // Validate status transition if stage is being changed
        if ($leadPipelineStageId != $lead->lead_pipeline_stage_id) {

            try {
                LeadStatusTransitionValidator::validateTransition($lead,$leadPipelineStageId);
            } catch (ValidationException $e) {
                return response()->json([
                    'message' => 'Status transitie validatie gefaald: '.$e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }
        }
        Event::dispatch('lead.update.before', $leadId);

        // If requested, complete all open activities for this lead after successful update
        if ($closeOpenActivities) {
            try {
                $this->completeAllOpenActivitiesForLead($leadId);
            } catch (Exception $e) {
                // Log but don't fail the stage update response
                Log::warning('Failed to complete open activities during stage update', [
                    'lead_id' => $leadId,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            logger()->info('Do not close all activities during stage update');
        }
        $data = [];
        $data['lead_pipeline_stage_id'] = $leadPipelineStageId;
        if (!is_null($closedAt)) {
            $data['closed_at'] = $closedAt;
        }
        if (!is_null($lostReason)) {
            $data['lost_reason'] = $lostReason;
        }
        if (!is_null($userId)) {
            $data['user_id'] = $userId;
        }
        // update state after closing activiteit, otherwise new activities will be closed
        $lead = $this->leadRepository->update(
            array_merge($data, ['entity_type' => 'leads']),
            $leadId,
            array_keys($data)
        );

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
     * Search leads with advanced filtering and field normalization.
     *
     * This endpoint supports flexible search queries with field-specific tokens,
     * automatic normalization of convenience tokens, and user permission filtering.
     *
     * Query Parameters:
     * - `search` (string): Search query with field-specific tokens, separated by semicolons.
     *   Supported tokens:
     *   - `name:term` - Automatically expanded to search in first_name, last_name, and married_name
     *   - `email:term` - Normalized to search in emails JSON column
     *   - `phone:term` - Normalized to search in phones JSON column
     *   - `user.name:term` - Expanded to search in user.first_name and user.last_name
     *   - `first_name:term`, `last_name:term`, `married_name:term` - Direct field searches
     *   - `emails:term`, `phones:term` - Direct JSON column searches
     *   - Any other field:value pairs supported by the repository
     *
     * - `searchFields` (string): Specifies which fields to search and how, separated by semicolons.
     *   Format: `field:operator;field:operator`
     *   Operators: `like`, `=`, `in`, etc.
     *   Example: `first_name:like;last_name:like;emails:like`
     *
     * - `searchJoin` (string): How to combine multiple search tokens.
     *   Values: `and` (default) or `or`
     *   Note: Automatically set to `or` when using convenience tokens (name, email, phone, user.name)
     *
     * Usage Examples:
     *
     * 1. Search by name (automatically searches first_name, last_name, married_name):
     *    GET /admin/leads/search?search=name:John;searchFields=first_name:like;last_name:like;married_name:like;searchJoin=or
     *
     * 2. Search by email address:
     *    GET /admin/leads/search?search=email:test@example.com;searchFields=emails:like;searchJoin=or
     *
     * 3. Search by phone number:
     *    GET /admin/leads/search?search=phone:0612345678;searchFields=phones:like;searchJoin=or
     *
     * 4. Search by user name:
     *    GET /admin/leads/search?search=user.name:Smith;searchFields=user.first_name:like;user.last_name:like;searchJoin=or
     *
     * 5. Combined search (multiple fields):
     *    GET /admin/leads/search?search=first_name:John;last_name:Doe;emails:test@example.com;searchFields=first_name:like;last_name:like;emails:like;searchJoin=or
     *
     * 6. Simple query parameter (for backward compatibility):
     *    GET /admin/leads/search?query=John
     *    Note: This will be normalized to search in name fields with LIKE operator.
     *
     * Response:
     * Returns a JSON collection of LeadLookupResource objects (minimal data) matching the search criteria.
     * Results are automatically filtered by user permissions if applicable.
     * Uses minimal resource to avoid N+1 queries.
     *
     * @return AnonymousResourceCollection|JsonResponse
     *         Collection of LeadLookupResource objects, or JsonResponse with error if validation fails
     */
    public function search(): AnonymousResourceCollection|JsonResponse
    {
        return $this->performAdvancedSearch(
            repository: $this->leadRepository,
            getFieldsSearchable: fn() => $this->leadRepository->getFieldsSearchable(),
            eagerLoadRelations: ['stage', 'user'],
            getResults: function ($repository, $emailTerms = [], $phoneTerms = []) {
                // Always apply RequestCriteria (with normalized search/searchFields)
                $repository->pushCriteria(app(RequestCriteria::class));

                // Apply email/phone search after RequestCriteria but before permission filter
                // This ensures email/phone search is combined with OR to name search
                if (!empty($emailTerms) || !empty($phoneTerms)) {
                    $this->applyEmailPhoneSearch($repository, $emailTerms, $phoneTerms);
                }

                // Apply permission filter via a Criteria so it composes with existing scopeQuery (email/phone)
                $this->applyPermissionFilter($repository);

                return $repository->all();
            },
            resourceClass: LeadLookupResource::class,
            queryParams: request()->query->all()
        );
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
        } catch (Exception $th) {
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
        } catch (Exception $exception) {
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
            ]
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

            $pipeline = $this->pipelineRepository->getDefaultPipeline(PipelineType::LEAD);
            /** @var \Webkul\Lead\Models\Pipeline $pipeline */

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
            $pipeline = $this->pipelineRepository->getDefaultPipeline(PipelineType::LEAD);
        }

        // Get first stage if no specific stage was requested
        if (!$defaultStageId && $pipeline) {
            /** @var \Webkul\Lead\Models\Pipeline $pipeline */
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
        $openActivities = $this->activityRepository
            ->where('lead_id', $leadId)
            ->where('is_done', 0)
            ->get();

        foreach ($openActivities as $activity) {
            logger()->info('Auto closing activities for lead #' . $activity->id);
            $this->activityRepository->update([
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

    /**
     * Validate LeadForm using LeadValidationService and add controller-specific rules.
     */
    private function validateLeadRequest(LeadForm $request, bool $isCreate): void
    {
        try {
            $rules = LeadValidationService::getWebValidationRules($request, $isCreate);

            // UI-only field used to carry contact person value in some forms
            $rules['contact_person_id_display'] = 'nullable|string';

            $this->validate($request, $rules);
        } catch (ValidationException $exception) {
            // Helps when UI doesn't show errors due to missing logging/visibility
            logger()->warning('Validation error during lead '.($isCreate ? 'create' : 'update'), [
                'errors' => $exception->errors(),
            ]);

            throw $exception;
        }
    }

    /**
     * Prepare request data for create/update (normalization and UI field mapping).
     */
    private function prepareLeadDataForUpsert(LeadForm $request, bool $isCreate): array
    {
        $data = $request->all();

        // UI-only create flag; never persist on lead.
        unset($data['create_person_from_lead']);

        // Handle contact_person_id - if empty but display has value, use display value
        if (isset($data['contact_person_id_display']) && $data['contact_person_id_display'] !== '') {
            $data['contact_person_id'] = $data['contact_person_id_display'];
        }
        unset($data['contact_person_id_display']);

        // Handle contact_person_id - convert empty string / '0' to null
        if (array_key_exists('contact_person_id', $data) && (empty($data['contact_person_id']) || $data['contact_person_id'] === '0')) {
            $data['contact_person_id'] = null;
        }

        // Handle empty date field
        if (array_key_exists('date_of_birth', $data) && ($data['date_of_birth'] === '' || $data['date_of_birth'] === null)) {
            $data['date_of_birth'] = null;
        }

        // Normalize empty strings and placeholders to null for foreign keys and enums
        foreach (['user_id', 'organization_id', 'lead_channel_id', 'lead_source_id', 'lead_type_id'] as $nullableKey) {
            if (array_key_exists($nullableKey, $data) && ($data[$nullableKey] === '' || $data[$nullableKey] === '?' || $data[$nullableKey] === null)) {
                $data[$nullableKey] = null;
            }
        }
        foreach (['salutation', 'gender', 'mri_status'] as $enumKey) {
            if (!array_key_exists($enumKey, $data)) {
                continue;
            }

            if ($data[$enumKey] === '' || $data[$enumKey] === '?') {
                $data[$enumKey] = null;
            } elseif ($data[$enumKey] instanceof BackedEnum) {
                $data[$enumKey] = $data[$enumKey]->value;
            }
        }

        // Ensure department_id is consistently typed or null
        if (array_key_exists('department_id', $data) && ($data['department_id'] === '' || $data['department_id'] === null)) {
            $data['department_id'] = null;
        }

        // Create-only fields could be handled here if needed in the future
        return $data;
    }

    /**
     * Apply pipeline + stage selection rules and mutate $data accordingly.
     *
     * - When $departmentDeterminesPipeline=true, department dictates pipeline. Stage will be kept only
     *   if it belongs to that pipeline; otherwise we pick the first stage of that pipeline.
     * - Otherwise, when stage is provided we infer pipeline from stage; else we fall back to
     *   department mapping or default pipeline.
     */
    private function applyPipelineAndStageSelection(array &$data, bool $departmentDeterminesPipeline): \Webkul\Lead\Models\Stage
    {
        $stage = null;
        $pipeline = null;
        /** @var \Webkul\Lead\Models\Pipeline|null $pipeline */

        if ($departmentDeterminesPipeline) {
            $departmentId = isset($data['department_id']) ? (int) $data['department_id'] : null;
            $pipeline = $departmentId ? $this->getPipelineForDepartmentId($departmentId) : null;

            if (!$pipeline) {
                $pipeline = $this->pipelineRepository->getDefaultPipeline(PipelineType::LEAD);
            }
            /** @var \Webkul\Lead\Models\Pipeline $pipeline */

            // If stage is provided, keep it only when it belongs to the department-driven pipeline.
            if (isset($data['lead_pipeline_stage_id'])) {
                $candidate = $this->stageRepository->find($data['lead_pipeline_stage_id']);
                if ($candidate && (int) $candidate->lead_pipeline_id === (int) $pipeline->id) {
                    $stage = $candidate;
                }
            }

            if (!$stage) {
                $stage = $pipeline->stages()->first();
            }

            $data['lead_pipeline_id'] = $pipeline->id;
            $data['lead_pipeline_stage_id'] = $stage->id;

            return $stage;
        }

        // Default behavior: stage overrides pipeline; otherwise pipeline is determined by department/default.
        if (isset($data['lead_pipeline_stage_id'])) {
            $stage = $this->stageRepository->findOrFail($data['lead_pipeline_stage_id']);

            // If pipeline_id is also provided, validate that stage belongs to pipeline
            if (isset($data['lead_pipeline_id']) && (int) $stage->lead_pipeline_id !== (int) $data['lead_pipeline_id']) {
                throw new InvalidArgumentException('The selected stage does not belong to the specified pipeline.');
            }

            $data['lead_pipeline_id'] = $stage->lead_pipeline_id;

            return $stage;
        }

        // Determine pipeline based on department_id if provided
        if (isset($data['department_id']) && $data['department_id'] !== null) {
            $pipeline = $this->getPipelineForDepartmentId((int) $data['department_id']);
        }

        // Fall back to default pipeline if not found
        if (!$pipeline) {
            $pipeline = $this->pipelineRepository->getDefaultPipeline(PipelineType::LEAD);
        }
        /** @var \Webkul\Lead\Models\Pipeline $pipeline */

        $stage = $pipeline->stages()->first();

        $data['lead_pipeline_id'] = $pipeline->id;
        $data['lead_pipeline_stage_id'] = $stage->id;

        return $stage;
    }

    private function getPipelineForDepartmentId(int $departmentId): ?Pipeline
    {
        if ($departmentId === Department::findHerniaId()) {
            return $this->pipelineRepository->find(PipelineDefaultKeys::PIPELINE_HERNIA_ID->value);
        }

        if ($departmentId === Department::findPrivateScanId()) {
            return $this->pipelineRepository->find(PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value);
        }

        throw new Exception('Invalid department id: '.$departmentId);
    }

    /**
     * Standard success responder for AJAX vs HTML requests.
     *
     * @param string $message Fully translated message.
     * @param string $redirectRoute Route name.
     * @param array $redirectParams Route parameters.
     */
    private function respondSuccess(string $message, string $redirectRoute, array $redirectParams = []): RedirectResponse|JsonResponse
    {
        $returnUrl = $this->resolveReturnUrl();
        $defaultRedirect = route($redirectRoute, $redirectParams);
        $redirect = $returnUrl ?: $defaultRedirect;

        if (request()->ajax()) {
            return response()->json([
                'message' => $message,
                'redirect' => $redirect,
            ]);
        }

        session()->flash('success', $message);

        if ($returnUrl) {
            return redirect($returnUrl);
        }

        return redirect()->route($redirectRoute, $redirectParams);
    }

    /**
     * Proxy download of a diagnosis form PDF via the forms API.
     */
    public function downloadDiagnosisForm(int $id, FormService $formService)
    {
        $lead = $this->leadRepository->findOrFail($id);

        if (! $lead->diagnosis_form_id) {
            abort(404, 'Lead has no diagnosis form.');
        }

        try {
            $response = $formService->downloadForm($lead->diagnosis_form_id);
        } catch (Exception $e) {
            Log::error('LeadController: Failed to download diagnosis form', [
                'lead_id'           => $id,
                'diagnosis_form_id' => $lead->diagnosis_form_id,
                'error'             => $e->getMessage(),
            ]);

            abort(502, 'Could not download diagnosis form.');
        }

        if (! $response->successful()) {
            abort($response->status(), 'Forms API returned an error.');
        }

        return response($response->body(), 200, [
            'Content-Type'        => $response->header('Content-Type') ?: 'application/pdf',
            'Content-Disposition' => $response->header('Content-Disposition') ?: 'attachment; filename="diagnosis-form-'.$lead->diagnosis_form_id.'.pdf"',
        ]);
    }
}
