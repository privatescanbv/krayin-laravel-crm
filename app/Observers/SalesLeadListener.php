<?php

namespace App\Observers;

use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStageDefaultKeys;
use Illuminate\Support\Facades\Log;
use Webkul\Attribute\Models\Attribute;
use Webkul\Attribute\Models\AttributeValue;
use Webkul\Lead\Models\Lead;

class SalesLeadListener
{
    /**
     * Handle the event.
     */
    public function handle(Lead $lead): void
    {
        // Skip workflow processing if webhooks are disabled (e.g., during imports)
        if (! config('webhook.enabled', true)) {
            Log::info('SalesLeadListener: Skipping - webhooks are disabled (likely during import)', [
                'lead_id' => $lead->id,
            ]);

            return;
        }

        // Refresh the lead to get the latest data from database
        $lead->refresh();

        logger()->info('SalesLeadListener triggered', [
            'lead_id'           => $lead->id,
            'stage'             => $lead->stage?->name,
            'pipeline_id'       => $lead->lead_pipeline_id,
            'pipeline_stage_id' => $lead->lead_pipeline_stage_id,
        ]);

        // Check if the pipeline was recently updated (within last 5 seconds)
        // This prevents double updates when AttributeValueObserver has already updated the pipeline
        $recentlyUpdated = $lead->updated_at && $lead->updated_at->diffInSeconds(now()) < 5;

        if ($recentlyUpdated) {
            Log::info('SalesLeadListener: Skipping update - lead was recently updated', [
                'lead_id'     => $lead->id,
                'updated_at'  => $lead->updated_at,
                'seconds_ago' => $lead->updated_at->diffInSeconds(now()),
            ]);

            return;
        }

        // Eager load the source relation
        $lead->load('source');

        $departmentValue = $this->getDepartmentValue($lead);
        if (! empty($departmentValue)) {
            // Update lead pipeline and stage based on department
            $this->leadDepartmentUpdated($lead, $departmentValue);
        } else {
            Log::info('SalesLeadListener: No department value found, skipping update', [
                'lead_id' => $lead->id,
            ]);
        }
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

        Log::info('SalesLeadListener: Checking if update is needed', [
            'lead_id'                    => $lead->id,
            'department'                 => $department,
            'current_pipeline_id'        => $lead->lead_pipeline_id,
            'current_pipeline_stage_id'  => $lead->lead_pipeline_stage_id,
            'expected_pipeline_id'       => $leadPipelineId,
            'expected_pipeline_stage_id' => $leadPipelineStageId,
            'pipeline_needs_update'      => $lead->lead_pipeline_id !== $leadPipelineId,
            'stage_needs_update'         => $lead->lead_pipeline_stage_id !== $leadPipelineStageId,
        ]);

        if ($lead->lead_pipeline_stage_id !== $leadPipelineStageId || $lead->lead_pipeline_id !== $leadPipelineId) {
            // Only update if the pipeline or stage is actually different
            Log::info('SalesLeadListener: Updating lead pipeline and stage based on department', [
                'lead_id'                    => $lead->id,
                'department'                 => $department,
                'lead_pipeline_id'           => $lead->lead_pipeline_id,
                'lead_pipeline_stage_id'     => $lead->lead_pipeline_stage_id,
                'new_lead_pipeline_id'       => $leadPipelineId,
                'new_lead_pipeline_stage_id' => $leadPipelineStageId,
            ]);

            $lead->update([
                'lead_pipeline_id'       => $leadPipelineId,
                'lead_pipeline_stage_id' => $leadPipelineStageId,
            ]);
        } else {
            Log::info('SalesLeadListener: No update needed - pipeline and stage already correct', [
                'lead_id'    => $lead->id,
                'department' => $department,
            ]);
        }
    }
}
