<?php

namespace App\Observers;

use App\Services\WebhookService;
use Illuminate\Support\Facades\Log;
use Webkul\Lead\Models\Lead;

class LeadObserver
{
    /**
     * Create a new observer instance.
     */
    public function __construct(
        protected WebhookService $webhookService
    ) {}

    /**
     * Handle the Lead "updated" event.
     */
    public function updated(Lead $lead): void
    {
        Log::info('UPDATE lead', [
            'lead_id' => $lead->id,
            'old_stage' => $lead->getOriginal('lead_pipeline_stage_id'),
            'new_stage' => $lead->lead_pipeline_stage_id
        ]);

        // Check if the stage has changed
        if ($lead->isDirty('lead_pipeline_stage_id')) {
            $this->webhookService->sendWebhook([
                'entity_id' => $lead->id,
                'status' => $lead->stage->name,
                'workflow_type' => $lead->workflow_type,
            ]);
        }
    }
}
