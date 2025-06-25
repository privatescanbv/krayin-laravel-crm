<?php

namespace App\Observers;

use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStageDefaultKeys;
use App\Enums\WebhookType;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Log;
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

        $departmentValue = $this->getDepartmentValue($lead);
        if (! empty($departmentValue)) {
            // Update lead pipeline and stage based on department
            $this->leadDepartmentUpdated($lead, $departmentValue);
        }
        $this->webhookService->sendWebhook([
            'entity_id'      => $lead->id,
            'status'         => $lead->stage->code,
            'source_code'    => $lead->source?->name,
            'source_code_id' => $lead->source?->id,
            'department'     => $departmentValue,
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

    private function leadDepartmentUpdated(Lead $lead, string $department): void
    {
        $leadPipelineId = PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value;
        $leadPipelineStageId = PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_PRIVATESCAN_ID->value;
        if ($department === 'Hernia') {
            $leadPipelineId = PipelineDefaultKeys::PIPELINE_HERNIA_ID->value;
            $leadPipelineStageId = PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_HERNIA_ID->value;
        }
        Log::info('Updating lead pipeline and stage based on department', [
            'lead_id'                => $lead->id,
            'department'             => $department,
            'lead_pipeline_id'       => $leadPipelineId,
            'lead_pipeline_stage_id' => $leadPipelineStageId,
        ]);
        $lead->update([
            'lead_pipeline_id'       => $leadPipelineId,
            'lead_pipeline_stage_id' => $leadPipelineStageId,
        ]);
    }
}
