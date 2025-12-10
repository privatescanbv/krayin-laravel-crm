<?php

namespace App\Observers;

use App\Actions\Sales\SalesToLostAction;
use App\Enums\WebhookType;
use App\Models\SalesLead;
use App\Services\WebhookService;

/**
 * Observer for SalesLead model to handle pipeline stage changes and webhooks.
 */
class SalesLeadObserver
{
    /**
     * Create a new observer instance.
     */
    public function __construct(
        protected WebhookService $webhookService,
        private readonly SalesToLostAction $salesToLostAction,
    ) {}

    /**
     * Handle the SalesLead "created" event.
     */
    public function created(SalesLead $salesLead): void
    {
        $this->sendWebhook($salesLead, 'SalesLeadObserver@created');
    }

    /**
     * Handle the SalesLead "updated" event.
     */
    public function updated(SalesLead $salesLead): void
    {
        if ($salesLead->pipeline_stage_id !== $salesLead->getOriginal('pipeline_stage_id')) {
            // Send webhook if stage has changed and the stage is actually different
            if ($salesLead->isDirty('pipeline_stage_id')) {
                $this->sendWebhook($salesLead, 'SalesLeadObserver@updated');
            }
            if ($salesLead->pipelineStage->is_lost) {
                $result = $this->salesToLostAction->execute($salesLead);
            }
        }
    }

    private function sendWebhook(SalesLead $salesLead, string $caller): void
    {

        $this->webhookService->sendWebhook([
            'entity_id'      => $salesLead->id,
            'status'         => $salesLead->pipelineStage?->code,
            'source_code'    => $salesLead->lead?->source?->name,
            'source_code_id' => $salesLead->lead?->source?->id,
            'department'     => $salesLead->lead?->department?->name,
            'lead_id'        => $salesLead->lead_id,
        ],
            WebhookType::SALES_LEAD_PIPELINE_STAGE_CHANGE,
            $caller);
    }
}
