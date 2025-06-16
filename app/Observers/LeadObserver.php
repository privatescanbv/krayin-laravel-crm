<?php

namespace App\Observers;

use App\Enums\LeadPipelineStage;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Log;
use Webkul\Lead\Models\Lead;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LeadObserver
{
    /**
     * Create a new observer instance.
     */
    public function __construct(
        protected WebhookService $webhookService
    ) {}

    /**
     * Handle the Lead "updating" event.
     */
    public function updating(Lead $lead): void
    {
        Log::info('UPDATE lead', [
            'lead_id' => $lead->id,
            'old_stage' => $lead->getOriginal('lead_pipeline_stage_id'),
            'new_stage' => $lead->lead_pipeline_stage_id
        ]);

        // Check if the stage has changed
        if ($lead->isDirty('lead_pipeline_stage_id')) {
            $newStageCode = $lead->stage?->code;

            // If changing to 'klant_adviseren' stage
            if ($newStageCode === LeadPipelineStage::ADVICE->value) {
                // Check if person_id is set
                if (!$lead->person_id) {
                    Log::warning('Cannot update lead: missing person_id for advice stage', [
                        'lead_id' => $lead->id
                    ]);
                    throw new HttpException(422, 'Contactpersoon is verplicht in de status "Klant adviseren"');
                }
            }
        }
    }

    /**
     * Handle the Lead "updated" event.
     */
    public function updated(Lead $lead): void
    {
        // Only send webhook if stage has changed
        if ($lead->isDirty('lead_pipeline_stage_id')) {
            $this->webhookService->sendWebhook([
                'entity_id' => $lead->id,
                'status' => $lead->stage->name,
                'workflow_type' => $lead->workflow_type,
            ]);
        }
    }
}
