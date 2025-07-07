<?php

namespace App\Http\Controllers\Admin;

use App\DataGrids\WorkflowLeadDataGrid;
use App\Enums\PipelineType;
use App\Http\Controllers\Controller;
use App\Models\WorkflowLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WorkflowLeadController extends Controller
{
    public function index(Request $request)
    {
        // Get selected pipeline or default workflow pipeline
        if ($request->has('pipeline_id')) {
            $pipeline = app('Webkul\Lead\Repositories\PipelineRepository')->find($request->pipeline_id);
        } else {
            $pipeline = app('Webkul\Lead\Repositories\PipelineRepository')->getDefaultPipelineByType(PipelineType::WORKFLOW);
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
            $dataGrid = app(WorkflowLeadDataGrid::class);

            return $dataGrid->toJson();
        }

        // For kanban view, return workflow leads grouped by pipeline stages
        $pipelineRepository = app('Webkul\Lead\Repositories\PipelineRepository');

        // Get selected pipeline or default workflow pipeline
        if ($request->filled('pipeline_id')) {
            $pipeline = $pipelineRepository->find($request->pipeline_id);
        } else {
            $pipeline = $pipelineRepository->getDefaultPipelineByType(PipelineType::WORKFLOW);
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
            $query = WorkflowLead::with(['pipelineStage', 'lead.person', 'user'])
                ->where('pipeline_stage_id', $stage->id);
            $workflowLeads = $query->get();

            $workflowLeads = $workflowLeads->map(function ($workflowLead) {
                return [
                    'id'                => $workflowLead->id,
                    'name'              => $workflowLead->name,
                    'description'       => $workflowLead->description,
                    'pipeline_stage_id' => $workflowLead->pipeline_stage_id,
                    'pipeline_stage'    => $workflowLead->pipelineStage ? [
                        'id'   => $workflowLead->pipelineStage->id,
                        'name' => $workflowLead->pipelineStage->name,
                    ] : null,
                    'lead' => $workflowLead->lead ? [
                        'id'     => $workflowLead->lead->id,
                        'title'  => $workflowLead->lead->title,
                        'person' => $workflowLead->lead->person ? [
                            'id'   => $workflowLead->lead->person->id,
                            'name' => $workflowLead->lead->person->name.'123',
                        ] : null,
                    ] : null,
                    'user' => $workflowLead->user ? [
                        'id'   => $workflowLead->user->id,
                        'name' => $workflowLead->user->name,
                    ] : null,
                    'created_at' => $workflowLead->created_at,
                ];
            });

            $data[$stage->sort_order] = [
                'id'         => $stage->id,
                'name'       => $stage->name,
                'sort_order' => $stage->sort_order,
                'leads'      => [
                    'data' => $workflowLeads,
                    'meta' => [
                        'total'        => $workflowLeads->count(),
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

        $workflowLead = WorkflowLead::create($request->all());

        return redirect()->route('admin.workflow-leads.index')
            ->with('success', 'Workflow lead created successfully.');
    }

    public function edit($id)
    {
        Log::info('WorkflowLeadController::edit called with ID: '.$id);

        $workflowLead = WorkflowLead::findOrFail($id);

        Log::info('WorkflowLead found: ', [
            'id'          => $workflowLead->id,
            'name'        => $workflowLead->name,
            'description' => $workflowLead->description,
        ]);

        return view('admin.workflow_leads.edit', ['workflowLead' => $workflowLead]);
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

        $workflowLead = WorkflowLead::findOrFail($id);
        $workflowLead->update($request->all());

        // If this is an AJAX request (like from kanban drag & drop), return JSON
        if ($request->ajax()) {
            return response()->json([
                'message'       => 'Workflow lead updated successfully.',
                'workflow_lead' => $workflowLead,
            ]);
        }

        return redirect()->route('admin.workflow-leads.index')
            ->with('success', 'Workflow lead updated successfully.');
    }

    public function view($id)
    {
        Log::info('WorkflowLeadController::edit called with ID 1111: '.$id);
        $workflowLead = WorkflowLead::with(['pipelineStage', 'lead', 'user'])->findOrFail($id);
        Log::info('WorkflowLeadController::edit called with ID: '.$id);

        return view('admin.workflow_leads.view', ['workflowLead' => $workflowLead]);
    }

    public function delete($id)
    {
        $workflowLead = WorkflowLead::findOrFail($id);
        $workflowLead->delete();

        return redirect()->route('admin.workflow-leads.index')
            ->with('success', 'Workflow lead deleted successfully.');
    }

    /**
     * Temporary debug method to test data structure
     */
    public function debug($id)
    {
        $workflowLead = WorkflowLead::with(['pipelineStage', 'lead.person', 'user'])->findOrFail($id);

        return response()->json([
            'workflow_lead' => [
                'id'          => $workflowLead->id,
                'name'        => $workflowLead->name,
                'description' => $workflowLead->description,
                'lead'        => $workflowLead->lead ? [
                    'id'     => $workflowLead->lead->id,
                    'title'  => $workflowLead->lead->title,
                    'person' => $workflowLead->lead->person ? [
                        'id'   => $workflowLead->lead->person->id,
                        'name' => $workflowLead->lead->person->name,
                    ] : null,
                ] : null,
                'user' => $workflowLead->user ? [
                    'id'   => $workflowLead->user->id,
                    'name' => $workflowLead->user->name,
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
                'filterable_options'    => app('Webkul\Lead\Repositories\StageRepository')->all(['name as label', 'id as value'])->toArray(),
                'allow_multiple_values' => true,
                'sortable'              => true,
                'visibility'            => true,
            ],
        ];
    }
}
