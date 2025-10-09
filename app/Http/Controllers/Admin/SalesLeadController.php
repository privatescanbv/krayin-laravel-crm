<?php

namespace App\Http\Controllers\Admin;

use App\DataGrids\SalesLeadDataGrid;
use App\Enums\PipelineType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SalesLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Models\Activity;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\StageRepository;

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

        return view('admin.sales_leads.index', [
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
        return view('admin.sales_leads.create');
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

        return view('admin.sales_leads.edit', ['workflowLead' => $salesLead]);
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

        // First load the sales lead to check if it has a lead_id
        $salesLead = SalesLead::with(['pipelineStage.pipeline.stages', 'user'])->findOrFail($id);

        // If there's no related lead_id, return 404
        if (! $salesLead->lead_id) {
            Log::warning('SalesLead found but no lead_id set', [
                'sales_lead_id'   => $id,
                'sales_lead_name' => $salesLead->name,
                'sales_lead_data' => $salesLead->toArray(),
            ]);

            return response()->view('errors.404', [
                'message' => 'Deze workflow lead heeft geen gekoppelde lead. Workflow Lead: '.$salesLead->name,
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

        return view('admin.sales_leads.view', [
            'workflowLead' => $salesLead,
            'lead'         => $lead,
            'orders'       => $orders,
        ]);
    }

    public function updateStage($id)
    {
        request()->validate([
            'lead_pipeline_stage_id' => 'required|exists:lead_pipeline_stages,id',
        ]);

        $salesLead = SalesLead::findOrFail($id);
        $salesLead->update([
            'pipeline_stage_id' => request('lead_pipeline_stage_id'),
        ]);

        return response()->json([
            'message' => 'Workflow lead stage updated successfully.',
        ]);
    }

    public function activities($id)
    {
        // Get activities related to this sales lead (not paginated, same as lead activities)
        $activities = Activity::where('workflow_lead_id', $id)
            ->with('emails')
            ->get();

        return \Webkul\Admin\Http\Resources\ActivityResource::collection($activities);
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

        $salesLead = SalesLead::findOrFail($id);

        $activity = Activity::create([
            'type'             => request('type'),
            'title'            => request('title'),
            'comment'          => request('description'),
            'user_id'          => request('user_id') ?? auth()->id(),
            'workflow_lead_id' => $id,
            'schedule_from'    => request('schedule_from'),
            'schedule_to'      => request('schedule_to'),
            'is_done'          => 0,
        ]);

        return response()->json([
            'message' => 'Activity created successfully.',
            'data'    => $activity,
        ]);
    }

    public function detachEmail($id, $emailId)
    {
        // Placeholder for email detachment
        return response()->json([
            'message' => 'Email detached successfully.',
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
     * Search sales leads.
     */
    public function search()
    {
        $search = request()->query('search', '');

        $query = SalesLead::with(['pipelineStage', 'lead', 'user']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $salesLeads = $query->limit(10)->get();

        return response()->json($salesLeads->map(function ($salesLead) {
            return [
                'id'          => $salesLead->id,
                'name'        => $salesLead->name,
                'description' => $salesLead->description,
                'stage'       => $salesLead->pipelineStage ? [
                    'id'   => $salesLead->pipelineStage->id,
                    'name' => $salesLead->pipelineStage->name,
                ] : null,
            ];
        }));
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
                'filterable_options'    => app(StageRepository::class)->all(['name as label', 'id as value'])->toArray(),
                'allow_multiple_values' => true,
                'sortable'              => true,
                'visibility'            => true,
            ],
        ];
    }
}
