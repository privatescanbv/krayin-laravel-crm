<?php

namespace App\Http\Controllers\Admin;

use App\DataGrids\SalesLeadDataGrid;
use App\Enums\PipelineType;
use App\Http\Controllers\Controller;
use App\Models\SalesLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SalesLeadController extends Controller
{
    public function index(Request $request)
    {
        // Get selected pipeline or default workflow pipeline
        if ($request->has('pipeline_id')) {
            $pipeline = app('Webkul\Lead\Repositories\PipelineRepository')->find($request->pipeline_id);
        } else {
            $pipeline = app('Webkul\Lead\Repositories\PipelineRepository')->getDefaultPipelineByType(PipelineType::BACKOFFICE);
        }

        // Remember selected pipeline for subsequent data requests
        if ($pipeline) {
            session(['workflow_pipeline_id' => $pipeline->id]);
        }

        $stages = $pipeline->stages->map(function ($stage) {
            return [
                'id'         => $stage->id,
                'name'       => $stage->name,
                'sort_order' => $stage->sort_order,
                'leads'      => [
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

        return view('admin.workflow_leads.index', [
            'pipeline' => $pipeline,
            'columns'  => $this->getKanbanColumns(),
            'stages'   => $stages,
        ]);
    }

    public function get(Request $request)
    {
        if ($request->has('view_type') && $request->view_type === 'table') {
            $dataGrid = app(SalesLeadDataGrid::class);

            return $dataGrid->toJson();
        }

        // For kanban view, return workflow leads grouped by pipeline stages
        $pipelineRepository = app('Webkul\Lead\Repositories\PipelineRepository');

        // Get selected pipeline or default workflow pipeline
        if ($request->filled('pipeline_id')) {
            $pipeline = $pipelineRepository->find($request->pipeline_id);
        } else {
            // Fallback to last selected pipeline from session
            $selectedPipelineId = session('workflow_pipeline_id');
            $pipeline = $selectedPipelineId
                ? $pipelineRepository->find($selectedPipelineId)
                : $pipelineRepository->getDefaultPipelineByType(PipelineType::BACKOFFICE);
        }

        if (! $pipeline) {
            Log::error('No default pipeline found for workflow leads.');

            return response()->json([
                'error' => 'No workflow pipeline found for this request.',
            ], 404);
        }

        $stages = $pipeline->stages;
        $data = [];

        foreach ($stages as $stage) {
            $query = SalesLead::with(['pipelineStage', 'lead', 'user'])
                ->where('pipeline_stage_id', $stage->id);
            $salesLeads = $query->get();

            $salesLeads = $salesLeads->map(function ($salesLead) {
                $person = $salesLead->lead ? $salesLead->lead->persons()->first() : null;

                return [
                    'id'                => $salesLead->id,
                    'name'              => $salesLead->name,
                    'description'       => $salesLead->description,
                    'pipeline_stage_id' => $salesLead->pipeline_stage_id,
                    'pipeline_stage'    => $salesLead->pipelineStage ? [
                        'id'   => $salesLead->pipelineStage->id,
                        'name' => $salesLead->pipelineStage->name,
                    ] : null,
                    'lead' => $salesLead->lead ? [
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
                    'created_at' => $salesLead->created_at,
                ];
            });

            $data[$stage->sort_order] = [
                'id'         => $stage->id,
                'name'       => $stage->name,
                'sort_order' => $stage->sort_order,
                'leads'      => [
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

    public function create(Request $request)
    {
        return view('admin.workflow_leads.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'pipeline_stage_id' => 'nullable|exists:lead_pipeline_stages,id',
            'lead_id'           => 'nullable|exists:leads,id',
            'user_id'           => 'nullable|exists:users,id',
        ]);

        $salesLead = SalesLead::create($request->all());

        return redirect()->route('admin.workflow-leads.index')
            ->with('success', 'Workflow lead created successfully.');
    }

    public function edit($id)
    {
        Log::info('SalesLeadController::edit called with ID: '.$id);

        $salesLead = SalesLead::findOrFail($id);

        Log::info('SalesLead found: ', [
            'id'          => $salesLead->id,
            'name'        => $salesLead->name,
            'description' => $salesLead->description,
        ]);

        return view('admin.workflow_leads.edit', ['workflowLead' => $salesLead]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'              => 'sometimes|required|string|max:255',
            'description'       => 'nullable|string',
            'pipeline_stage_id' => 'sometimes|nullable|exists:lead_pipeline_stages,id',
            'lead_id'           => 'nullable|exists:leads,id',
            'user_id'           => 'nullable|exists:users,id',
        ]);

        $salesLead = SalesLead::findOrFail($id);
        $salesLead->update($request->all());

        // If this is an AJAX request (like from kanban drag & drop), return JSON
        if ($request->ajax()) {
            return response()->json([
                'message'       => 'Workflow lead updated successfully.',
                'workflow_lead' => $salesLead,
            ]);
        }

        return redirect()->route('admin.workflow-leads.index')
            ->with('success', 'Workflow lead updated successfully.');
    }

    public function view($id)
    {
        Log::info('SalesLeadController::view called with ID: '.$id);
        
        // Load sales lead with related lead and all its relationships
        $salesLead = SalesLead::with([
            'pipelineStage',
            'user',
            'lead' => function ($query) {
                $query->with([
                    'address',
                    'organization',
                    'source',
                    'type',
                    'channel',
                    'department',
                    'user',
                    'tags',
                    'persons',
                    'pipeline',
                    'stage',
                ]);
            }
        ])->findOrFail($id);

        // If there's no related lead, return 404 or create error view
        if (!$salesLead->lead) {
            Log::warning('SalesLead found but no related lead', [
                'sales_lead_id' => $id,
                'sales_lead_name' => $salesLead->name,
                'sales_lead_data' => $salesLead->toArray()
            ]);
            
            return response()->view('errors.404', [
                'message' => 'Deze workflow lead heeft geen gekoppelde lead. Workflow Lead: ' . $salesLead->name
            ], 404);
        }

        $lead = $salesLead->lead;

        return view('admin.workflow_leads.view', [
            'workflowLead' => $salesLead,
            'lead' => $lead
        ]);
    }

    public function delete($id)
    {
        $salesLead = SalesLead::findOrFail($id);
        $salesLead->delete();

        return redirect()->route('admin.workflow-leads.index')
            ->with('success', 'Workflow lead deleted successfully.');
    }

    /**
     * Temporary debug method to test data structure
     */
    public function debug($id)
    {
        $salesLead = SalesLead::with(['pipelineStage', 'lead', 'user'])->findOrFail($id);

        $person = $salesLead->lead ? $salesLead->lead->persons()->first() : null;

        return response()->json([
            'workflow_lead' => [
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
     * Get kanban columns for filtering.
     */
    private function getKanbanColumns(): array
    {
        return [
            [
                'index'                 => 'id',
                'label'                 => 'ID',
                'type'                  => 'integer',
                'searchable'            => false,
                'search_field'          => 'in',
                'filterable'            => true,
                'filterable_type'       => null,
                'filterable_options'    => [],
                'allow_multiple_values' => true,
                'sortable'              => true,
                'visibility'            => true,
            ],
            [
                'index'                 => 'user_id',
                'label'                 => 'User',
                'type'                  => 'string',
                'searchable'            => false,
                'search_field'          => 'in',
                'filterable'            => true,
                'filterable_type'       => 'searchable_dropdown',
                'filterable_options'    => [
                    'repository' => \App\Models\User::class,
                    'column'     => [
                        'label' => 'name',
                        'value' => 'id',
                    ],
                ],
                'allow_multiple_values' => true,
                'sortable'              => true,
                'visibility'            => true,
            ],
            [
                'index'                 => 'pipeline_stage_id',
                'label'                 => 'Pipeline Stage',
                'type'                  => 'string',
                'searchable'            => false,
                'search_field'          => 'in',
                'filterable'            => true,
                'filterable_type'       => 'dropdown',
                'filterable_options'    => app('Webkul\\Lead\\Repositories\\StageRepository')->all(['name as label', 'id as value'])->toArray(),
                'allow_multiple_values' => true,
                'sortable'              => true,
                'visibility'            => true,
            ],
        ];
    }
}
