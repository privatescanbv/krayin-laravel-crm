<?php

namespace Webkul\Admin\Http\Controllers\Lead;

use App\Enums\DuplicateEntityType;
use App\Services\DuplicateFalsePositiveService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Resources\LeadResource;
use Webkul\Lead\Models\Lead;
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
     *
     * Optional query param `with` injects a manually chosen lead into the merge screen
     * (used by the manual "Lead samenvoegen" flow) without changing automatic detection.
     *
     * Redirects instead of rendering when the manually picked lead needs to become primary
     * (see the sales-lead swap below).
     */
    public function index(int $leadId): View|RedirectResponse
    {
        $lead = $this->leadRepository->with(['stage', 'pipeline', 'user', 'organization', 'contactPerson'])->findOrFail($leadId);
        $duplicates = $this->leadRepository->findPotentialDuplicates($lead);

        $preselectedLeadIds = [];
        $manualLeadId = (int) request()->query('with', 0);

        if ($manualLeadId > 0 && $manualLeadId !== $leadId) {
            $manualLead = $this->leadRepository
                ->with(['stage', 'pipeline', 'user', 'organization', 'contactPerson'])
                ->find($manualLeadId);

            if ($manualLead) {
                // The manually picked lead already carries a sales lead (order/invoicing) and the
                // current lead does not - merging would fail (guardAgainstMergingSalesLeads) because
                // this lead would be the one archived. Swap roles instead of letting the user hit
                // that error: reload with the sales-lead lead as primary and this page's lead as the
                // one to merge in. If both sides have a sales lead, no swap can save it - that's
                // caught below where the row gets disabled with an explanation.
                $salesLeadIds = $this->leadRepository->leadIdsWithSalesLead([$leadId, $manualLeadId]);

                if ($salesLeadIds->contains($manualLeadId) && ! $salesLeadIds->contains($leadId)) {
                    return redirect()->route('admin.leads.duplicates.index', [
                        'id'   => $manualLeadId,
                        'with' => $leadId,
                    ]);
                }

                if (! $duplicates->contains('id', $manualLeadId)) {
                    $duplicates = $duplicates->prepend($manualLead)->values();
                }

                // Both sides have a sales lead: no swap fixes that, leave it unchecked. The row
                // below still gets has_sales_lead so its checkbox is disabled with an explanation
                // instead of preselecting a choice the user can no longer untick.
                if (! $salesLeadIds->contains($manualLeadId)) {
                    $preselectedLeadIds[] = $manualLeadId;
                }
            }
        }

        // Leads that already have a sales lead can never be the side a merge archives - flagged per
        // row so the Vue table disables selecting them as a duplicate (see leadIdsWithSalesLead).
        $salesLeadDuplicateIds = $this->leadRepository->leadIdsWithSalesLead($duplicates->pluck('id')->all());

        // Use LeadResource for consistent data formatting
        $leadData = array_merge((new LeadResource($lead))->resolve(), $this->mergeScreenFields($lead));

        // Compute per-duplicate match reasons
        $primaryEmails = $this->extractValues($leadData['emails'] ?? []);
        $primaryPhones = $this->extractValues($leadData['phones'] ?? []);

        // Populate primary lead signals so UI doesn't show '-'
        $leadData['matched_emails'] = $primaryEmails;
        $leadData['matched_phones'] = array_map(fn($p) => $this->normalizePhone($p), $primaryPhones);
        $leadData['name_reason']    = null; // not applicable for primary itself

        $duplicatesData = [];
        foreach ($duplicates as $dup) {
            $dupData = array_merge((new LeadResource($dup))->resolve(), $this->mergeScreenFields($dup));
            $reasons = $this->computeReasons($leadData, $dupData, $primaryEmails, $primaryPhones);

            $dupData['matched_emails'] = $reasons['email'];
            $dupData['matched_phones'] = $reasons['phone'];
            $dupData['name_reason']    = $reasons['name_reason'];
            $dupData['has_sales_lead'] = $salesLeadDuplicateIds->contains($dup->id);

            $duplicatesData[] = $dupData;
        }

        return view('admin::leads.duplicates.index', [
            'lead' => $lead,
            'duplicates' => $duplicates,
            'leadData' => $leadData,
            'duplicatesData' => $duplicatesData,
            'preselectedLeadIds' => $preselectedLeadIds,
        ]);
    }

    /**
     * Manual merge entry: search and select another lead to merge with the current one.
     */
    public function select(int $leadId): View
    {
        $lead = $this->leadRepository->with(['stage', 'user'])->findOrFail($leadId);

        return view('admin::leads.duplicates.select', [
            'lead' => $lead,
        ]);
    }

    /**
     * Fields that are selectable on the merge screen but are not part of LeadResource.
     *
     * They are added here instead of in the resource on purpose: LeadResource is also used by
     * EmailResource and the person lookup, and the BSN has no business in those payloads.
     *
     * @return array<string, mixed>
     */
    private function mergeScreenFields(Lead $lead): array
    {
        return [
            'national_identification_number' => $lead->national_identification_number,
            'organization_id'                => $lead->organization_id,
            'organization_name'              => $lead->organization?->name,
            'contact_person_id'              => $lead->contact_person_id,
            'contact_person_name'            => $lead->contactPerson?->name,
            // The portal form id and the website PDF are merged as a single choice, so they are
            // shown - and compared - as one readable value.
            'diagnosis_form'                 => $this->describeDiagnosisForm($lead),
        ];
    }

    private function describeDiagnosisForm(Lead $lead): string
    {
        $parts = array_filter([
            $lead->diagnosis_form_id ? 'Formulier #'.$lead->diagnosis_form_id : null,
            $lead->diagnoseform_pdf_url ? 'PDF' : null,
        ]);

        return $parts ? implode(' + ', $parts) : 'Geen';
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
    public function merge(int $id): JsonResponse
    {
        $this->validate(request(), [
            'primary_lead_id' => 'required|exists:leads,id',
            'duplicate_lead_ids' => 'required|array|min:1',
            'duplicate_lead_ids.*' => 'exists:leads,id',
            'field_mappings' => 'nullable|array',
        ]);

        if ($id !== (int) request('primary_lead_id')) {
            return response()->json([
                'success' => false,
                'message' => __('messages.lead.merge_failed', ['error' => 'Lead in URL komt niet overeen met de primaire lead.']),
            ], 422);
        }

        $primaryLeadId = request('primary_lead_id');
        $duplicateLeadIds = request('duplicate_lead_ids');
        $fieldMappings = request('field_mappings', []);

        try {
            $mergedLead = $this->leadRepository->mergeLeads($primaryLeadId, $duplicateLeadIds, $fieldMappings);

            return response()->json([
                'success' => true,
                'message' => __('messages.lead.merge_success'),
                'merged_lead' => [
                    'id' => $mergedLead->id,
                    'title' => $mergedLead->title,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.lead.merge_failed', ['error' => $e->getMessage()]),
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
