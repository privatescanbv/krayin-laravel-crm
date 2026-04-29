<?php

namespace App\Observers;

use App\Actions\Sales\SalesToLostAction;
use App\Enums\Departments;
use App\Enums\PipelineDefaultKeys;
use App\Enums\WebhookType;
use App\Models\SalesLead;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Event;
use Webkul\Lead\Models\Stage;

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
     * Handle the SalesLead "updating" event.
     * When department_id changes, reset pipeline_stage_id to the default stage of the new sales pipeline.
     */
    public function updating(SalesLead $salesLead): void
    {
        if (! $salesLead->isDirty('department_id')) {
            return;
        }

        $salesLead->load('department');
        $isHernia = $salesLead->department?->name === Departments::HERNIA->value;

        $pipelineId = $isHernia
            ? PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value
            : PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID->value;

        $defaultStage = Stage::where('lead_pipeline_id', $pipelineId)
            ->where('is_default', true)
            ->first();

        if ($defaultStage) {
            $salesLead->pipeline_stage_id = $defaultStage->id;
        }
    }

    /**
     * Handle the SalesLead "created" event.
     */
    public function created(SalesLead $salesLead): void
    {
        Event::dispatch('sale.update_stage.after', $salesLead);
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
                Event::dispatch('sale.update_stage.after', $salesLead);
                $this->sendWebhook($salesLead, 'SalesLeadObserver@updated');
            }
            if ($salesLead->stage->is_lost) {
                $this->salesToLostAction->execute($salesLead);
            }
        }
    }

    private function sendWebhook(SalesLead $salesLead, string $caller): void
    {

        $this->webhookService->sendWebhook([
            'entity_id'      => $salesLead->id,
            'status'         => $salesLead->stage?->code,
            'source_code'    => $salesLead->lead?->source?->name,
            'source_code_id' => $salesLead->lead?->source?->id,
            'department'     => $salesLead->lead?->department?->name,
            'lead_id'        => $salesLead->lead_id,
        ],
            WebhookType::SALES_LEAD_PIPELINE_STAGE_CHANGE,
            $caller);
    }
}
