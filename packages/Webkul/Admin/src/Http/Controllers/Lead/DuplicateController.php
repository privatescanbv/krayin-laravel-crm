<?php

namespace Webkul\Admin\Http\Controllers\Lead;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Lead\Repositories\LeadRepository;

class DuplicateController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected LeadRepository $leadRepository
    ) {
    }

    /**
     * Show potential duplicates for a lead.
     */
    public function index(int $leadId): View
    {
        $lead = $this->leadRepository->with(['person', 'stage', 'pipeline', 'user'])->findOrFail($leadId);
        $duplicates = $this->leadRepository->findPotentialDuplicates($lead);

        // Convert lead data for JavaScript
        $leadData = $this->formatLeadForJs($lead);
        $duplicatesData = $duplicates->map(function($duplicate) {
            return $this->formatLeadForJs($duplicate);
        });

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
            'duplicates' => $duplicates->map(function ($duplicate) {
                return [
                    'id' => $duplicate->id,
                    'title' => $duplicate->title,
                    'first_name' => $duplicate->first_name,
                    'last_name' => $duplicate->last_name,
                    'emails' => $duplicate->emails,
                    'phones' => $duplicate->phones,
                    'person_name' => $duplicate->person?->name,
                    'stage_name' => $duplicate->stage?->name,
                    'pipeline_name' => $duplicate->pipeline?->name,
                    'created_at' => $duplicate->created_at?->format('Y-m-d H:i:s'),
                ];
            }),
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
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to merge leads: ' . $e->getMessage(),
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
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    /**
     * Format lead data for JavaScript consumption.
     */
    private function formatLeadForJs($lead): array
    {
        return [
            'id' => $lead->id,
            'title' => $lead->title,
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'emails' => is_array($lead->emails) ? $lead->emails : [],
            'phones' => is_array($lead->phones) ? $lead->phones : [],
            'pipeline' => $lead->pipeline ? [
                'id' => $lead->pipeline->id,
                'name' => $lead->pipeline->name,
            ] : null,
            'stage' => $lead->stage ? [
                'id' => $lead->stage->id,
                'name' => $lead->stage->name,
            ] : null,
            'person' => $lead->person ? [
                'id' => $lead->person->id,
                'name' => $lead->person->name,
            ] : null,
            'created_at' => $lead->created_at ? $lead->created_at->format('Y-m-d H:i:s') : null,
        ];
    }
}