<?php

namespace App\Observers;

use App\Enums\LeadAttributeKeys;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStageDefaultKeys;
use Illuminate\Support\Facades\Log;
use Webkul\Attribute\Models\AttributeOption;
use Webkul\Attribute\Models\AttributeValue;
use Webkul\Lead\Models\Lead;

class AttributeValueObserver
{
    /**
     * Handle the AttributeValue "created" event.
     */
    public function created(AttributeValue $attributeValue): void
    {
        $attribute = $attributeValue->attribute;

        $this->handleAttributeChanged($attribute, $attributeValue);
    }

    /**
     * Handle the AttributeValue "updated" event.
     */
    public function updated(AttributeValue $attributeValue): void
    {
        $attribute = $attributeValue->attribute;
        Log::info('AttributeValue updated', [
            'attribute_code'  => $attribute->code,
            'attribute_value' => $attributeValue->text_value,
            'entity_id'       => $attributeValue->entity_id,
            'entity_type'     => $attributeValue->entity_type,
        ]);
        $this->handleAttributeChanged($attribute, $attributeValue);
    }

    /**
     * Handle the AttributeValue "deleted" event.
     */
    public function deleted(AttributeValue $attributeValue): void
    {
        //
    }

    /**
     * Handle the AttributeValue "restored" event.
     */
    public function restored(AttributeValue $attributeValue): void
    {
        //
    }

    private function leadDepartmentUpdated(Lead $lead, string $department): void
    {
        $leadPipelineId = PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value;
        $leadPipelineStageId = PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_PRIVATESCAN_ID->value;
        if ($department === 'Hernia') {
            $leadPipelineId = PipelineDefaultKeys::PIPELINE_HERNIA_ID->value;
            $leadPipelineStageId = PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_HERNIA_ID->value;
        }

        Log::info('AttributeValueObserver: Checking if update is needed', [
            'lead_id'                    => $lead->id,
            'department'                 => $department,
            'current_pipeline_id'        => $lead->lead_pipeline_id,
            'current_pipeline_stage_id'  => $lead->lead_pipeline_stage_id,
            'expected_pipeline_id'       => $leadPipelineId,
            'expected_pipeline_stage_id' => $leadPipelineStageId,
            'pipeline_needs_update'      => $lead->lead_pipeline_id !== $leadPipelineId,
            'stage_needs_update'         => $lead->lead_pipeline_stage_id !== $leadPipelineStageId,
        ]);

        if ($leadPipelineId != $lead->lead_pipeline_id) {
            Log::info('AttributeValueObserver: Updating lead pipeline and stage based on department', [
                'lead_id'                => $lead->id,
                'department'             => $department,
                'lead_pipeline_id'       => $leadPipelineId,
                'lead_pipeline_stage_id' => $leadPipelineStageId,
            ]);
            $lead->update([
                'lead_pipeline_id'       => $leadPipelineId,
                'lead_pipeline_stage_id' => $leadPipelineStageId,
            ]);
        } else {
            Log::info('AttributeValueObserver: No update needed - pipeline already correct', [
                'lead_id'    => $lead->id,
                'department' => $department,
            ]);
        }
    }

    private function handleAttributeChanged(mixed $attribute, AttributeValue $attributeValue): void
    {
        if ($attribute->entity_type === 'leads') {
            if ($attribute->code === LeadAttributeKeys::DEPARTMENT->value) {
                $lead = Lead::find($attributeValue->entity_id);
                if (! empty($attributeValue->integer_value)) {
                    $this->leadDepartmentUpdated($lead, AttributeOption::find($attributeValue->integer_value)->name);
                }
            }
        }
    }
}
