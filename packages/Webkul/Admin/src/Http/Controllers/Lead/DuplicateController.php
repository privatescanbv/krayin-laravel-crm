<?php

namespace Webkul\Admin\Http\Controllers\Lead;

use App\Enums\DuplicateEntityType;
use App\Services\DuplicateFalsePositiveService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Resources\LeadResource;
use Webkul\Lead\Repositories\LeadRepository;
use App\Services\DuplicateReasonHelpers;

class DuplicateController extends Controller
{
    use DuplicateReasonHelpers;
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected LeadRepository $leadRepository,
        protected DuplicateFalsePositiveService $falsePositiveService
    ) {
    }

    /**
     * Show potential duplicates for a lead.
     */
    public function index(int $leadId): View
    {
        $lead = $this->leadRepository->with(['stage', 'pipeline', 'user'])->findOrFail($leadId);
        $duplicates = $this->leadRepository->findPotentialDuplicates($lead);

        // Use LeadResource for consistent data formatting
        $leadData = (new LeadResource($lead))->resolve();

        // Compute per-duplicate match reasons
        $primaryEmails = $this->extractValues($leadData['emails'] ?? []);
        $primaryPhones = $this->extractValues($leadData['phones'] ?? []);

        // Populate primary lead signals so UI doesn't show '-'
        $leadData['matched_emails'] = $primaryEmails;
        $leadData['matched_phones'] = array_map(fn($p) => $this->normalizePhone($p), $primaryPhones);
        $leadData['name_reason']    = null; // not applicable for primary itself

        $duplicatesData = [];
        foreach ($duplicates as $dup) {
            $dupData = (new LeadResource($dup))->resolve();
            $reasons = $this->computeReasons($leadData, $dupData, $primaryEmails, $primaryPhones);

            $dupData['matched_emails'] = $reasons['email'];
            $dupData['matched_phones'] = $reasons['phone'];
            $dupData['name_reason']    = $reasons['name_reason'];

            $duplicatesData[] = $dupData;
        }

        return view('admin::leads.duplicates.index', [
            'lead' => $lead,
            'duplicates' => $duplicates,
            'leadData' => $leadData,
            'duplicatesData' => $duplicatesData,
        ]);
    }

    /**
     * Get potential duplicates for a lead via AJAX.
     */
    public function getDuplicates(int $leadId): JsonResponse
    {
        $lead = $this->leadRepository->findOrFail($leadId);
        $duplicates = $this->leadRepository->findPotentialDuplicates($lead);

        return response()->json([
            'duplicates' => LeadResource::collection($duplicates),
            'count' => $duplicates->count(),
        ]);
    }

    /**
     * Merge selected leads.
     */
    public function merge(): JsonResponse
    {
        $this->validate(request(), [
            'primary_lead_id' => 'required|exists:leads,id',
            'duplicate_lead_ids' => 'required|array|min:1',
            'duplicate_lead_ids.*' => 'exists:leads,id',
            'field_mappings' => 'nullable|array',
        ]);

        $primaryLeadId = request('primary_lead_id');
        $duplicateLeadIds = request('duplicate_lead_ids');
        $fieldMappings = request('field_mappings', []);

        try {
            $mergedLead = $this->leadRepository->mergeLeads($primaryLeadId, $duplicateLeadIds, $fieldMappings);

            return response()->json([
                'success' => true,
                'message' => 'Leads successfully merged.',
                'merged_lead' => [
                    'id' => $mergedLead->id,
                    'title' => $mergedLead->title,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to merge leads: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark selected leads as "not a duplicate" (false positive) for duplicate detection.
     */
    public function markFalsePositive(int $leadId): JsonResponse
    {
        $this->validate(request(), [
            'entity_ids' => 'required|array|min:2',
            'entity_ids.*' => 'integer|distinct|exists:leads,id',
        ]);

        $entityIds = array_map('intval', request('entity_ids', []));

        // Ensure the selection is anchored to the current lead page (prevents cross-entity misuse from UI).
        if (! in_array($leadId, $entityIds, true)) {
            return response()->json([
                'success' => false,
                'message' => 'De selectie moet de primaire lead bevatten.',
            ], 422);
        }

        try {
            $pairs = $this->falsePositiveService->storeForEntities(
                DuplicateEntityType::LEAD,
                $entityIds,
                null
            );

            return response()->json([
                'success' => true,
                'message' => 'Geselecteerde leads gemarkeerd als geen duplicaat.',
                'pairs'   => $pairs,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Opslaan false positive mislukt: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if a lead has potential duplicates (for AJAX calls).
     */
    public function checkDuplicates(int $leadId): JsonResponse
    {
        $lead = $this->leadRepository->findOrFail($leadId);
        $hasDuplicates = $this->leadRepository->hasPotentialDuplicates($lead);
        $duplicatesCount = $hasDuplicates ? $this->leadRepository->findPotentialDuplicates($lead)->count() : 0;

        return response()->json([
            'has_duplicates' => $hasDuplicates,
            'duplicates_count' => $duplicatesCount,
        ]);
    }

    /**
     * Debug lead data to identify foreach issues.
     */
    public function debug(int $leadId): JsonResponse
    {
        try {
            $lead = $this->leadRepository->findOrFail($leadId);
            $debugData = $this->leadRepository->debugLeadData($lead);

            return response()->json([
                'success' => true,
                'debug_data' => $debugData,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }


}
