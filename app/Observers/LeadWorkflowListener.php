<?php

namespace App\Observers;

use App\Enums\WebhookType;
use App\Services\WebhookService;
use Webkul\Attribute\Models\Attribute;
use Webkul\Attribute\Models\AttributeValue;
use Webkul\Lead\Models\Lead;

class LeadWorkflowListener
{
    public function __construct(
        protected WebhookService $webhookService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(Lead $lead): void
    {
        // Eager load the source relation
        $lead->load('source');

        $this->webhookService->sendWebhook([
            'entity_id'      => $lead->id,
            'status'         => $lead->stage->code,
            'source_code'    => $lead->source?->name,
            'source_code_id' => $lead->source?->id,
            'department'     => $this->getDepartmentValue($lead),
        ],
            WebhookType::LEAD_PIPELINE_STAGE_CHANGE);
    }

    private function getDepartmentValue(Lead $lead): ?string
    {
        // Get department attribute ID
        $departmentAttribute = Attribute::where('code', 'department')
            ->where('entity_type', 'leads')
            ->firstOrFail();

        return AttributeValue::query()
            ->select(['attribute_options.name'])
            ->where('attribute_values.entity_id', $lead->id)
            ->where('attribute_values.attribute_id', $departmentAttribute->id)
            ->join('attribute_options', 'attribute_values.integer_value', '=', 'attribute_options.id')
            ->first()?->name;
    }
}
