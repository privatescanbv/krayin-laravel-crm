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
     * @var array<int, true>
     */
    private static array $pipelineStageSavePending = [];

    public function __construct(
        protected WebhookService $webhookService,
        private readonly SalesToLostAction $salesToLostAction,
    ) {}

    public function saving(SalesLead $salesLead): void
    {
        if ($salesLead->exists && $salesLead->isDirty('pipeline_stage_id')) {
            self::$pipelineStageSavePending[$salesLead->id] = true;
        }
    }

    /**
     * When department_id changes, reset pipeline_stage_id to the default stage of the new sales pipeline.
     */
    public function updating(SalesLead $salesLead): void
    {
        if ($salesLead->isDirty('pipeline_stage_id')) {
            self::$pipelineStageSavePending[$salesLead->id] = true;
        }

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
            self::$pipelineStageSavePending[$salesLead->id] = true;
        }
    }

    public function created(SalesLead $salesLead): void
    {
        Event::dispatch('sale.update_stage.after', $salesLead);
        $this->sendWebhook($salesLead, 'SalesLeadObserver@created');
    }

    public function updated(SalesLead $salesLead): void
    {
        $changes = $salesLead->getChanges();

        if (self::$pipelineStageSavePending[$salesLead->id] ?? false) {
            unset(self::$pipelineStageSavePending[$salesLead->id]);

            Event::dispatch('sale.update_stage.after', $salesLead);
            $this->sendWebhook($salesLead, 'SalesLeadObserver@updated');
        }

        $salesLead->load('stage');

        if ($salesLead->stage?->is_lost
            && (array_key_exists('pipeline_stage_id', $changes) || array_key_exists('lost_reason', $changes))) {
            $this->salesToLostAction->execute($salesLead);
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
