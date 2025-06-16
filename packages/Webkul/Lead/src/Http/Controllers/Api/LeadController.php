<?php

namespace Webkul\Lead\Http\Controllers\Api;

use App\Enums\LeadPipelineStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Support\Facades\Log;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Tag\Repositories\TagRepository;
use Webkul\User\Models\User;

class LeadController extends Controller
{
    use ValidatesRequests;

    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected LeadRepository $leadRepository,
        protected PersonRepository $personRepository,
        protected TagRepository $tagRepository
    ) {}

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
    public function store(Request $request): JsonResponse
    {
        Log::info('create lead', [
            'request' => $request->all(),
        ]);

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
            'entity_type' => 'leads',
            'lead_pipeline_stage_id' => 1, // Set to first stage
            'status' => 1,
            'lead_type_id' => 1, // Default lead type
        ]);

        $lead = $this->leadRepository->create($leadData);

        // Add tags if provided
        if ($request->has('tags')) {
            $tagIds = [];
            foreach ($request->tags as $tagName) {
                // Find or create tag
                $tag = $this->tagRepository->firstOrCreate([
                    'user_id' => $currentUserId,
                    'name' => $tagName
                ]);
                $tagIds[] = $tag->id;
            }

            // Sync tags
            $lead->tags()->sync($tagIds);
        }

        return response()->json([
            'message' => 'Lead created successfully.',
            'data' => $lead->load('tags'),
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
    public function update(Request $request, int $id): JsonResponse
    {
        $this->validate($request, [
            'title' => 'required',
        ]);

        $lead = $this->leadRepository->update($request->all(), $id);

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

        $lead = $this->leadRepository->findOrFail($id);
        
        $lead = $this->leadRepository->update([
            'lead_pipeline_stage_id' => $request->lead_pipeline_stage_id,
            'entity_type' => 'leads'
        ], $id);

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
        $this->leadRepository->delete($id);

        return response()->json([
            'message' => 'Lead deleted successfully.',
        ]);
    }
}
