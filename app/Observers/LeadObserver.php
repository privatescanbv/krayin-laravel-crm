<?php

namespace App\Observers;

use App\Actions\Leads\LeadToLostAction;
use App\Enums\LeadPipelineStageDefaults;
use App\Enums\PipelineDefaultKeys;
use App\Enums\WebhookType;
use App\Repositories\SalesLeadRepository;
use App\Services\LeadDuplicateCacheService;
use App\Services\WebhookService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Repositories\LeadRepository;

/**
 * Because some attributes of leads will be set by CRM workflows, we created @LeadWorkflowListener.
 * This Observer will be called before the CRM workflows.
 */
class LeadObserver
{
    /**
     * Create a new observer instance.
     */
    public function __construct(
        protected WebhookService $webhookService,
        protected ActivityRepository $activityRepository,
        private readonly LeadRepository $leadRepository,
        private readonly SalesLeadRepository $salesLeadRepository,
        private readonly LeadToLostAction $leadToLostAction
    ) {}

    /**
     * Handle the Lead "updating" event.
     */
    public function updating(Lead $lead): void
    {
        // Check if the stage has changed
        if ($lead->isDirty('lead_pipeline_stage_id')) {
            // Resolve the NEW stage code explicitly from the incoming stage id, as the relation may be stale during updating
            $newStageCode = Stage::find($lead->lead_pipeline_stage_id)?->code;

            // If changing to 'klant_adviseren' stage
            if ($newStageCode === LeadPipelineStageDefaults::ADVICE->value) {
                // Check if person_id is set
                if (! $lead->person_id) {
                    Log::warning('Cannot update lead: missing person_id for advice stage', [
                        'lead_id' => $lead->id,
                    ]);
                    throw new HttpException(422, 'Contactpersoon is verplicht in de status "Klant adviseren"');
                }
            }

            // If changing to any 'lost' stage, check if lost_reason is provided
            if ($newStageCode && Stage::find($lead->lead_pipeline_stage_id)?->is_lost) {
                if (empty($lead->lost_reason)) {
                    Log::warning('Cannot update lead: missing lost_reason for lost stage', [
                        'lead_id' => $lead->id,
                    ]);
                    throw new HttpException(422, 'Reden van verlies is verplicht bij status "Verloren"');
                }
                $this->leadToLostAction->execute($lead);
            }
        }
    }

    /**
     * Handle the Lead "created" event.
     */
    public function created(Lead $lead): void
    {
        // Set created_by if not already set
        if (is_null($lead->created_by) && auth()->check()) {
            DB::table('leads')->where('id', $lead->id)->update(['created_by' => auth()->id()]);
        }

        Log::info('CREATE lead', [
            'lead_id' => $lead->id,
            'stage'   => $lead->stage?->name,
        ]);

        // Check if pipeline will be updated to avoid duplicate webhooks
        $willUpdatePipeline = $this->willPipelineBeUpdated($lead);

        $this->setDefaultPipelineState($lead);

        // Only send webhook if pipeline wasn't updated (to avoid duplicate)
        // The updated observer will handle the webhook if pipeline changed
        if (! $willUpdatePipeline) {
            $this->sendWebhook($lead, 'LeadObserver@created');
        }

        // Invalidate duplicate cache for new lead
        $this->invalidateDuplicateCache($lead);
    }

    /**
     * Handle the Lead "updated" event.
     */
    public function updated(Lead $lead): void
    {
        // Set updated_by if authenticated user exists
        if (auth()->check()) {
            DB::table('leads')->where('id', $lead->id)->update(['updated_by' => auth()->id()]);
        }

        // Send webhook if stage has changed and the stage is actually different
        if ($lead->isDirty('lead_pipeline_stage_id') && $lead->lead_pipeline_stage_id !== $lead->getOriginal('lead_pipeline_stage_id') && $lead->stage) {

            logger()->info('lead update', [
                'lead_id'            => $lead->id,
                'original'           => $lead->getOriginal('lead_pipeline_stage_id'),
                'new'                => $lead->lead_pipeline_stage_id,
                'wasChanged'         => $lead->wasChanged('lead_pipeline_stage_id'),
                'changed_attributes' => $lead->getChanges(),
                'dirty_attributes'   => $lead->getDirty(),
            ]);
            // 'stack_trace' => (new \Exception())->getTraceAsString(),
            $this->sendWebhook($lead, 'LeadObserver@updated');

            // If stage transitioned to a "won" stage, create SalesLead and initial Order
            // Reload the stage relationship to get the fresh data
            $lead->load('stage');
            if ($lead->stage?->is_won) {
                $this->salesLeadRepository->createFromWonLead($lead);
            }
        }

        // Log activities for fixed fields
        $this->logFixedFieldsActivity($lead);

        // Invalidate duplicate cache when lead is updated
        $this->invalidateDuplicateCache($lead);
    }

    private function setDefaultPipelineState(Lead $lead): void
    {
        if (is_null($lead->department)) {
            logger()->error('lead has no department, cannot set default pipeline', [
                'lead_id' => $lead->id,
            ]);

            return;
        }
        [$leadPipelineId, $leadPipelineStageId] = $this->leadRepository->mapLeadPipelineLineByDepartment($lead->department);
        if ($lead->lead_pipeline_id != $leadPipelineId) {
            $lead = $this->leadRepository->findOrFail($lead->id);
            logger()->info('lead pipeline updated');
            $lead->update([
                'lead_pipeline_id'       => $leadPipelineId,
                'lead_pipeline_stage_id' => $leadPipelineStageId,
            ]);
        }
    }

    /**
     * Check if the pipeline will be updated for this lead
     */
    private function willPipelineBeUpdated(Lead $lead): bool
    {
        if (is_null($lead->department)) {
            return false;
        }

        $expectedPipelineId = PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value;
        if ($lead->department->name == 'Hernia') {
            $expectedPipelineId = PipelineDefaultKeys::PIPELINE_HERNIA_ID->value;
        }

        return $lead->lead_pipeline_id != $expectedPipelineId;
    }

    private function sendWebhook(Lead $lead, string $caller): void
    {
        // Eager load the source relation
        $lead->load('source');

        $departmentValue = $this->getDepartmentValue($lead);

        $this->webhookService->sendWebhook([
            'entity_id'      => $lead->id,
            'status'         => $lead->stage?->code,
            'source_code'    => $lead->source?->name,
            'source_code_id' => $lead->source?->id,
            'department'     => $departmentValue,
        ],
            WebhookType::LEAD_PIPELINE_STAGE_CHANGE,
            $caller);
    }

    private function getDepartmentValue(Lead $lead): ?string
    {
        return $lead->department?->name;
    }

    /**
     * Log activities for fixed fields (first_name, last_name, maiden_name)
     */
    private function logFixedFieldsActivity(Lead $lead): void
    {
        $fixedFields = ['first_name', 'last_name', 'maiden_name', 'description'];
        $fieldLabels = [
            'first_name'  => 'Voornaam',
            'last_name'   => 'Achternaam',
            'maiden_name' => 'Aangetrouwde naam',
            'description' => 'omschrijving',
        ];

        foreach ($fixedFields as $field) {
            if ($lead->wasChanged($field)) {
                $oldValue = $lead->getOriginal($field);
                $newValue = $lead->$field;

                // Skip if both values are empty/null
                if (empty($oldValue) && empty($newValue)) {
                    continue;
                }

                $fieldLabel = $fieldLabels[$field];

                $activity = $this->activityRepository->create([
                    'type'       => 'system',
                    'title'      => "$fieldLabel gewijzigd",
                    'is_done'    => 1,
                    'additional' => json_encode([
                        'attribute' => $fieldLabel,
                        'new'       => [
                            'value' => $newValue ?: '-',
                            'label' => $newValue ?: '-',
                        ],
                        'old' => [
                            'value' => $oldValue ?: '-',
                            'label' => $oldValue ?: '-',
                        ],
                    ]),
                    'user_id' => auth()->id() ?? 1,
                    'lead_id' => $lead->id,
                ]);
            }
        }
    }

    /**
     * Invalidate duplicate cache for a lead and related leads.
     */
    private function invalidateDuplicateCache(Lead $lead): void
    {
        try {
            $cacheService = app(LeadDuplicateCacheService::class);
            $cacheService->invalidateLeadCache($lead->id);
        } catch (Exception $e) {
            Log::warning('Failed to invalidate duplicate cache for lead '.$lead->id.': '.$e->getMessage());
        }
    }
}
