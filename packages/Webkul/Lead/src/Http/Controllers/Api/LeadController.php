<?php

namespace Webkul\Lead\Http\Controllers\Api;

use App\Enums\PipelineDefaultKeys;
use App\Enums\ContactLabel;
use App\Http\Requests\Api\HerniaCreateLeadRequest;
use App\Http\Requests\Api\PrivatescanCreateLeadRequest;
use App\Models\Department;
use App\Services\InboundLeads\InboundLeadPayloadMapper;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Webkul\Admin\Http\Requests\LeadForm;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Type;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\User\Models\User;
use Webkul\Admin\Http\Controllers\Lead\LeadController as AdminLeadController;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Illuminate\Support\Facades\DB;
use App\Services\LeadValidationService;

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
        protected AttributeValueRepository $attributeValueRepository,
        protected InboundLeadPayloadMapper $inboundLeadPayloadMapper
    )
    {
        request()->request->add(['entity_type' => 'leads']);
    }

    // Removed index method - too heavy, use /admin/leads/get for kanban instead

    /**
     * Store a newly created lead in storage.
     *
     * @response 201 {"message":"Lead created successfully.","lead_id":123,"data":{"id":123}}
     */
    public function store(LeadForm $request): JsonResponse
    {
        return $this->storeFromLeadForm($request);
    }

    /**
     * Create a Hernia lead from the inbound (Gravity Forms) payload schema.
     *
     * @response 201 {"message":"Lead created successfully.","lead_id":123,"data":{"id":123}}
     */
    public function storeHernia(HerniaCreateLeadRequest $inbound): JsonResponse
    {
        $mapped = $this->inboundLeadPayloadMapper->mapHernia($inbound->validated());

        $leadForm = LeadForm::createFrom($inbound);
        $leadForm->setContainer(app());
        $leadForm->replace($mapped);

        return $this->storeFromLeadForm($leadForm, forceDepartmentId: Department::findHerniaId());
    }

    /**
     * Create a Privatescan lead from the inbound (Web-to-person) payload schema.
     *
     * @response 201 {"message":"Lead created successfully.","lead_id":123,"data":{"id":123}}
     */
    public function storePrivatescan(PrivatescanCreateLeadRequest $inbound): JsonResponse
    {
        $mapped = $this->inboundLeadPayloadMapper->mapPrivatescan($inbound->validated());

        $leadForm = LeadForm::createFrom($inbound);
        $leadForm->setContainer(app());
        $leadForm->replace($mapped);

        return $this->storeFromLeadForm($leadForm, forceDepartmentId: Department::findPrivateScanId());
    }

    /**
     * Shared lead-create implementation for API endpoints.
     */
    private function storeFromLeadForm(LeadForm $request, ?int $forceDepartmentId = null): JsonResponse
    {
        // TODO replace with auth()->id
        $currentUserId = User::query()->first()?->id;

        try {
            $departmentId = $forceDepartmentId ?? Department::findPrivateScanId();

            if ($forceDepartmentId === null) {
                // Backwards compatible: infer Hernia department when lead type is "Operatie"
                if (isset($request['lead_type_id'])) {
                    $leadType = Type::query()->where('id', $request['lead_type_id'])->first();
                    if ($leadType && $leadType->name == 'Operatie') {
                        $departmentId = Department::findHerniaId();
                    }
                }
            }
        } catch (ModelNotFoundException $e) {
            Log::error('Could not find departments', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Internal server error, department not found',
                'data' => [],
            ], 500);
        }

        // Add required fields before validation
        $request->merge([
            'user_id' => $currentUserId,
            'status' => 1,
            'department_id' => $departmentId,
        ]);

        // Default anamnesis flags to "no" (false) for API if not provided
        $request->merge([
            'metals' => $request->has('metals') ? $request->input('metals') : false,
            'claustrophobia' => $request->has('claustrophobia') ? $request->input('claustrophobia') : false,
            'allergies' => $request->has('allergies') ? $request->input('allergies') : false,
        ]);

        // Ensure contacts are in array structure expected by validators
        // Map single 'email'/'phone' fields to emails[]/phones[] arrays if provided
        $incoming = $request->all();
        if (isset($incoming['email']) && !isset($incoming['emails'])) {
            $incoming['emails'] = [[
                'value' => (string) $incoming['email'],
                'label' => ContactLabel::default()->value,
                'is_default' => true,
            ]];
            unset($incoming['email']);
        }
        if (isset($incoming['phone']) && !isset($incoming['phones'])) {
            $incoming['phones'] = [[
                'value' => (string) $incoming['phone'],
                'label' => ContactLabel::default()->value,
                'is_default' => true,
            ]];
            unset($incoming['phone']);
        }
        $request->replace($incoming);

        // Normalize contact arrays before validation
        $this->normalizeContactArrays($request);

        try {
            $this->validate($request, LeadValidationService::getApiValidationRules($request));
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Lead creation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        // Create lead with person_id
        $leadData = $request->all();

        // Set the data via the global request (required by AdminLeadController internals)
        foreach ($leadData as $key => $value) {
            request()->request->add([$key => $value]);
        }
        request()->request->add(['entity_type' => 'leads']);

        // We need pipeline changes to trigger n8n. Lead should never be left on this pipeline stage.
        $request['lead_pipeline_stage_id'] = PipelineDefaultKeys::PIPELINE_TECHNICAL_STAGE_ID->value;
        $request['lead_pipeline_id'] = PipelineDefaultKeys::PIPELINE_TECHNICAL_ID->value;

        try {
            [$lead] = $this->leadService->storeLead($request);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Lead creation failed.',
                'errors' => [$e->getMessage()],
            ], 400);
        } catch (Exception $e) {
            Log::error('Could not store lead ', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTrace(),
            ]);

            return response()->json([
                'message' => 'Internal server error, could not store lead',
                'data' => [],
            ], 500);
        }

        return response()->json([
            'message' => 'Lead created successfully.',
            // Keep original structure for backwards compatibility, but also expose lead_id as top-level field.
            'lead_id' => $lead->id,
            'data' => ['id' => $lead->id],
        ], 201);
    }

    /**
     * Display the specified lead.
     */
    public function show(int $id): JsonResponse
    {
        $lead = $this->leadRepository->with(['address', 'organization'])->findOrFail($id);

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
                        $requestData['emails'][$index]['label'] = ContactLabel::default()->value;
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
                        $requestData['phones'][$index]['label'] = ContactLabel::default()->value;
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
            return ContactLabel::default()->value;
        }

        return ContactLabel::fromOld($label)->value;
    }
}
