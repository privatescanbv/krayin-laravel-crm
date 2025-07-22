<?php

namespace Webkul\Lead\Http\Controllers\Api;

use App\Enums\PipelineDefaultKeys;
use App\Models\Department;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Support\Facades\Log;
use Webkul\Admin\Http\Requests\LeadForm;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Type;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\User\Models\User;
use Webkul\Admin\Http\Controllers\Lead\LeadController as AdminLeadController;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Illuminate\Support\Facades\DB;

class LeadController extends Controller
{
    use ValidatesRequests;

    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected LeadRepository           $leadRepository,
        protected AdminLeadController      $leadService,
        protected AttributeRepository      $attributeRepository,
        protected AttributeValueRepository $attributeValueRepository
    )
    {
        request()->request->add(['entity_type' => 'leads']);
    }

    /**
     * Display a listing of the leads.
     */
    public function index(): JsonResponse
    {
        $leads = $this->leadRepository->all();

        return response()->json([
            'data' => $leads,
        ]);
    }

    /**
     * Store a newly created lead in storage.
     */
    public function store(LeadForm $request): JsonResponse
    {
        $this->validate($request, [
            'title' => 'required',
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email',
            'lead_source_id' => 'required:numeric',
            'lead_channel_id' => 'required:numeric',
            'lead_type_id' => 'required:numeric',
        ]);

        // TODO replace with auth()-> id
        $currentUserId = User::query()->first()?->id;

        try {
            $departmentId = Department::findPrivateScanId();
            if (Type::query()->where('id', $request['lead_type_id'])->firstOrFail()->name == 'Operatie') {
                $departmentId = Department::findHerniaId();
            }
        } catch (ModelNotFoundException $e) {
            Log::error('Could not find departments by name Hernia ', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Internal server error, department not found',
                'data' => [],
            ], 500);
        }
        // Create lead with person_id
        $leadData = array_merge($request->all(), [
            'user_id' => $currentUserId,
            'status' => 1,
            'department_id' => $departmentId
        ]);

        // Convert single email to emails array format expected by the admin controller
        if (isset($leadData['email']) && !isset($leadData['emails'])) {
            $leadData['emails'] = [
                [
                    'value' => $leadData['email'],
                    'label' => 'work',
                    'is_default' => true
                ]
            ];
            unset($leadData['email']);
        }

        // Set the data via the request
        foreach ($leadData as $key => $value) {
            request()->request->add([$key => $value]);
        }
        request()->request->add(['entity_type' => 'leads']);
        //we need pipeline changes, to trigger n8n. Lead should never be left on this pipeline stage.
        $request['lead_pipeline_stage_id'] = PipelineDefaultKeys::PIPELINE_TECHNICAL_STAGE_ID->value;
        $request['lead_pipeline_id'] = PipelineDefaultKeys::PIPELINE_TECHNICAL_ID->value;
        try {
            [$lead, $leadPipelineId] = $this->leadService->storeLead($request);
        } catch (Exception $e) {
            Log::error('Could not store lead ', [
                'error' => $e->getMessage(),
                'data' => $request,
                'trace' => $e->getTrace(),
            ]);
            return response()->json([
                'message' => 'Internal server error, could not store lead',
                'data' => [],
            ], 500);
        }
        return response()->json([
            'message' => 'Lead created successfully.',
            'data' => ['id' => $lead->id],
        ], 201);
    }

    /**
     * Display the specified lead.
     */
    public function show(int $id): JsonResponse
    {
        $lead = $this->leadRepository->with('address')->findOrFail($id);

        return response()->json([
            'data' => $lead,
        ]);
    }

    /**
     * Update the specified lead in storage.
     */
    public function update(LeadForm $request, int $id): JsonResponse
    {
        $lead = $this->leadService->update($request, $id);

        return response()->json([
            'message' => 'Lead updated successfully.',
            'data' => $lead,
        ]);
    }

    /**
     * Update the pipeline stage of a lead.
     */
    public function updateStage(Request $request, int $leadId): JsonResponse
    {
        $this->validate($request, [
            'lead_pipeline_stage_id' => 'required|exists:lead_pipeline_stages,id',
        ]);
        $lead = $this->leadService->updateStageId($leadId, request()->input('lead_pipeline_stage_id'));

        return response()->json([
            'message' => 'Lead stage updated successfully.',
            'data' => $lead,
        ]);
    }

    /**
     * Remove the specified lead from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->leadService->destroy($id);

        return response()->json([
            'message' => 'Lead deleted successfully.',
        ]);
    }

    public function nextStage(string $id)
    {
        $lead = Lead::findOrFail($id);

        // Get current stage directly from lead_pipeline_stages table
        $currentStage = DB::table('lead_pipeline_stages')
            ->where('id', $lead->lead_pipeline_stage_id)
            ->first();

        if (!$currentStage) {
            return response()->json([
                'message' => 'Current stage not found.',
            ], 404);
        }

        // Find next stage in the same pipeline
        $nextStage = DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', $lead->lead_pipeline_id)
            ->where('sort_order', '>', $currentStage->sort_order)
            ->orderBy('sort_order', 'asc')
            ->first();

        if (is_null($nextStage)) {
            return response()->json([
                'message' => 'No next stage found for this lead.',
            ], 404);
        }

        $lead = $this->leadService->updateStageId($id, $nextStage->id);

        return response()->json([
            'message' => 'Lead stage updated successfully.',
            'data' => $lead,
        ]);
    }
}
