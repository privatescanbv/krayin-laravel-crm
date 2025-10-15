<?php

namespace App\Observers;

use App\Enums\ActivityType;
use App\Enums\WebhookType;
use App\Models\SalesLead;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Models\Activity;

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
    ) {}

    /**
     * Handle the SalesLead "created" event.
     */
    public function created(SalesLead $salesLead): void
    {
        Log::info('CREATE sales lead', [
            'sales_lead_id' => $salesLead->id,
            'stage'         => $salesLead->pipelineStage?->name,
        ]);

        // Create a system activity on the related lead
        if ($salesLead->lead_id) {
            Activity::create([
                'lead_id'        => $salesLead->lead_id,
                'sales_lead_id'  => $salesLead->id,
                'type'           => ActivityType::SYSTEM,
                'title'          => 'Sales lead aangemaakt',
                'comment'        => 'Een nieuwe sales lead is aangemaakt voor deze lead.',
                'is_done'        => 1,
                'additional'     => [
                    'link' => route('admin.sales-leads.view', $salesLead->id),
                ],
            ]);
        }

        $this->sendWebhook($salesLead, 'SalesLeadObserver@created');
    }

    /**
     * Handle the SalesLead "updated" event.
     */
    public function updated(SalesLead $salesLead): void
    {
        // Send webhook if stage has changed and the stage is actually different
        if ($salesLead->isDirty('pipeline_stage_id') && $salesLead->pipeline_stage_id !== $salesLead->getOriginal('pipeline_stage_id') && $salesLead->pipelineStage) {

            logger()->info('sales lead update', [
                'sales_lead_id'      => $salesLead->id,
                'original'           => $salesLead->getOriginal('pipeline_stage_id'),
                'new'                => $salesLead->pipeline_stage_id,
                'wasChanged'         => $salesLead->wasChanged('pipeline_stage_id'),
                'changed_attributes' => $salesLead->getChanges(),
                'dirty_attributes'   => $salesLead->getDirty(),
            ]);

            $this->sendWebhook($salesLead, 'SalesLeadObserver@updated');
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
