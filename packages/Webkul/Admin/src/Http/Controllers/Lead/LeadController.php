<?php

namespace Webkul\Admin\Http\Controllers\Lead;

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
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Prettus\Repository\Criteria\RequestCriteria;
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

class LeadController extends Controller
{
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
        protected PersonRepository    $personRepository
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

        if (request('pipeline_id')) {
            $pipeline = $this->pipelineRepository->find(request('pipeline_id'));
        } else {
            $pipeline = $this->pipelineRepository->getDefaultPipeline();
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
            if (request()->query('pipeline_id')) {
                $pipeline = $this->pipelineRepository->find(request()->query('pipeline_id'));
            } else {
                $pipeline = $this->pipelineRepository->getDefaultPipeline();
            }

            if (!$pipeline) {
                \Log::error('No pipeline found for leads.get endpoint', [
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
        
        if ($stageId = request()->query('pipeline_stage_id')) {
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
        $user = auth()->user();
        $userGroupNames = $user->groups->pluck('name')->toArray();
        $defaultDepartmentId = Department::mapGroupToDepartmentId($userGroupNames);
        
        // Determine the pipeline based on URL parameters or user department
        $pipeline = null;
        $defaultStageId = null;
        
        // First check if pipeline_id is provided in URL
        if (request('pipeline_id')) {
            $pipeline = $this->pipelineRepository->find(request('pipeline_id'));
        }
        
        // If stage_id is provided, get pipeline from stage
        if (!$pipeline && request('stage_id')) {
            $stage = $this->stageRepository->find(request('stage_id'));
            if ($stage) {
                $pipeline = $stage->pipeline;
                $defaultStageId = $stage->id;
            }
        }
        
        // If no pipeline found yet, determine based on user department
        if (!$pipeline) {
            // Map department to pipeline
            if ($defaultDepartmentId == Department::findHerniaId()) {
                $pipeline = $this->pipelineRepository->find(\App\Enums\PipelineDefaultKeys::PIPELINE_HERNIA_ID->value);
            } else {
                $pipeline = $this->pipelineRepository->find(\App\Enums\PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value);
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
        
        return view('admin::leads.create', [
            'defaultDepartmentId' => $defaultDepartmentId,
            'defaultPipelineId' => $pipeline->id ?? null,
            'defaultStageId' => $defaultStageId
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LeadForm $request): RedirectResponse
    {
        // Normalize contact arrays before validation
        $this->normalizeContactArrays($request);

        $this->validate($request, LeadValidationService::getWebValidationRules($request));

            try {
                [$lead, $leadPipelineId] = $this->storeLead($request);

                session()->flash('success', trans('admin::app.leads.create-success'));

                return redirect()->route('admin.leads.index', ['pipeline_id' => $leadPipelineId]);
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

            // Handle empty date fields
            if (isset($data['date_of_birth']) && empty($data['date_of_birth'])) {
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

                $data['lead_pipeline_id'] = $stage->lead_pipeline_id;
            } else {
                // Determine pipeline based on department_id if provided
                $pipeline = null;
                
                if (isset($data['department_id'])) {
                    if ($data['department_id'] == Department::findHerniaId()) {
                        $pipeline = $this->pipelineRepository->find(\App\Enums\PipelineDefaultKeys::PIPELINE_HERNIA_ID->value);
                    } elseif ($data['department_id'] == Department::findPrivateScanId()) {
                        $pipeline = $this->pipelineRepository->find(\App\Enums\PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value);
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
            return redirect()->route('admin.leads.index');
        }

        return view('admin::leads.view', compact('lead'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(LeadForm $request, int $id): RedirectResponse|JsonResponse
    {
        try {
            $this->validate($request, LeadValidationService::getWebValidationRules($request));

            Event::dispatch('lead.update.before', $id);

            $data = $request->all();

            // Handle empty date fields
            if (isset($data['date_of_birth']) && empty($data['date_of_birth'])) {
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

                $data['lead_pipeline_id'] = $stage->lead_pipeline_id;
            } else {
                // Determine pipeline based on department_id if provided
                $pipeline = null;
                
                if (isset($data['department_id'])) {
                    if ($data['department_id'] == Department::findHerniaId()) {
                        $pipeline = $this->pipelineRepository->find(\App\Enums\PipelineDefaultKeys::PIPELINE_HERNIA_ID->value);
                    } elseif ($data['department_id'] == Department::findPrivateScanId()) {
                        $pipeline = $this->pipelineRepository->find(\App\Enums\PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value);
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
                    'message' => trans('admin::app.leads.update-success'),
                ]);
            }

            session()->flash('success', trans('admin::app.leads.update-success'));

            if (request()->has('closed_at')) {
                return redirect()->back();
            } else {
                return redirect()->route('admin.leads.index', ['pipeline_id' => $data['lead_pipeline_id']]);
            }
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
            ['code', 'NOTIN', ['title', 'description']],
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
            'lost_reason' => 'nullable|string',
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

        Event::dispatch('lead.update.before', $leadId);

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
    public function search(): AnonymousResourceCollection
    {
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
        } catch (\Exception $exception) {
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
     */
    private function normalizeContactArrays($request)
    {
        $requestData = $request->all();

        // Normalize emails
        if (isset($requestData['emails']) && is_array($requestData['emails'])) {
            foreach ($requestData['emails'] as $index => $email) {
                if (is_array($email)) {
                    // Ensure label exists and normalize it
                    if (!isset($email['label']) || empty($email['label'])) {
                        $requestData['emails'][$index]['label'] = 'work';
                    } else {
                        $requestData['emails'][$index]['label'] = $this->normalizeLabel($email['label']);
                    }

                    // Normalize is_default to boolean
                    if (isset($email['is_default'])) {
                        $requestData['emails'][$index]['is_default'] = $this->normalizeBoolean($email['is_default']);
                    } else {
                        $requestData['emails'][$index]['is_default'] = false;
                    }
                }
            }
        }

        // Normalize phones
        if (isset($requestData['phones']) && is_array($requestData['phones'])) {
            foreach ($requestData['phones'] as $index => $phone) {
                if (is_array($phone)) {
                    // Ensure label exists and normalize it
                    if (!isset($phone['label']) || empty($phone['label'])) {
                        $requestData['phones'][$index]['label'] = 'work';
                    } else {
                        $requestData['phones'][$index]['label'] = $this->normalizeLabel($phone['label']);
                    }

                    // Normalize is_default to boolean
                    if (isset($phone['is_default'])) {
                        $requestData['phones'][$index]['is_default'] = $this->normalizeBoolean($phone['is_default']);
                    } else {
                        $requestData['phones'][$index]['is_default'] = false;
                    }
                }
            }
        }

        // Replace the request data
        $request->replace($requestData);
    }

    /**
     * Normalize various representations to boolean
     */
    private function normalizeBoolean($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'on', 'yes']);
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        return false;
    }

    /**
     * Normalize label to lowercase and handle common variations
     */
    private function normalizeLabel(string $label): string
    {
        if (empty($label)) {
            return 'work';
        }

        // Convert to lowercase and map common variations
        $normalizedLabel = strtolower(trim($label));
        $labelMap = [
            'work' => 'work',
            'werk' => 'work',
            'home' => 'home',
            'thuis' => 'home',
            'mobile' => 'mobile',
            'mobiel' => 'mobile',
            'other' => 'other',
            'anders' => 'other'
        ];

        return $labelMap[$normalizedLabel] ?? 'work';
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

            Anamnesis::where('lead_id', $leadId)
                ->where('user_id', $personId)
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
}
