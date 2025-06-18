<?php

namespace Webkul\Lead\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Webkul\Admin\Http\Requests\LeadForm;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\User\Models\User;
use Webkul\Admin\Http\Controllers\Lead\LeadController as AdminLeadController;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;

class LeadController extends Controller
{
    use ValidatesRequests;

    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected LeadRepository $leadRepository,
        protected AdminLeadController $leadService,
        protected AttributeRepository $attributeRepository,
        protected AttributeValueRepository $attributeValueRepository
    ) {
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
            'lead_pipeline_id' => 'required',
            'lead_pipeline_stage_id' => 'required',
            'lead_source_id' => 'required:numeric',
        ]);

        // TODO replace with auth()-> id
        $currentUserId = User::query()->first()?->id;

        // Create lead with person_id
        $leadData = array_merge($request->all(), [
            'user_id' => $currentUserId,
            'status' => 1,
            'lead_type_id' => 1, // Default lead type
        ]);

        // Set the data via the request
        foreach ($leadData as $key => $value) {
            request()->request->add([$key => $value]);
        }
        $lead = $this->leadService->store($request);

        return response()->json([
            'message' => 'Lead created successfully.',
            'data' => $lead,
        ], 201);
    }

    /**
     * Display the specified lead.
     */
    public function show(int $id): JsonResponse
    {
        $lead = $this->leadRepository->findOrFail($id);

        return response()->json([
            'data' => $lead,
        ]);
    }

    /**
     * Update the specified lead in storage.
     */
    public function update(LeadForm $request, int $id): JsonResponse
    {
//        $this->validate($request, [
//            'title' => 'required',
//        ]);

        $lead = $this->leadService->update($request, $id);

        return response()->json([
            'message' => 'Lead updated successfully.',
            'data' => $lead,
        ]);
    }

    /**
     * Update the pipeline stage of a lead.
     */
    public function updateStage(Request $request, int $id): JsonResponse
    {
        $this->validate($request, [
            'lead_pipeline_stage_id' => 'required|exists:lead_pipeline_stages,id',
        ]);

        // Set the data via the request
        foreach ($request->all() as $key => $value) {
            request()->request->add([$key => $value]);
        }

        $lead = $this->leadService->updateStage($id);

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
}
