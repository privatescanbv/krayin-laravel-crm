<?php

namespace App\Observers;

use App\Enums\LeadAttributeKeys;
use App\Enums\PersonAttributeKeys;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStageDefaultKeys;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\Attribute\Models\AttributeOption;
use Webkul\Attribute\Models\AttributeValue;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

class AttributeValueObserver
{
    private array $personAttributeKeysPartOfFullName = [PersonAttributeKeys::FIRST_NAME->value, PersonAttributeKeys::LAST_NAME_PREFIX->value, PersonAttributeKeys::LAST_NAME->value, PersonAttributeKeys::NICKNAME->value];

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

    /**
     * Update the name field based on individual name attributes
     */
    private function updateNameField(Person $person): void
    {
        $attributeIds = DB::table('attributes')
            ->where('entity_type', 'persons')
            ->whereIn('code', $this->personAttributeKeysPartOfFullName)
            ->pluck('id', 'code');

        // Haal de actuele values op
        $values = AttributeValue::where('entity_type', 'persons')
            ->where('entity_id', $person->id)
            ->whereIn('attribute_id', $attributeIds->values())
            ->get()
            ->keyBy(function ($v) use ($attributeIds) {
                // Vind de code bij het attribute_id
                return $attributeIds->search($v->attribute_id);
            });

        $nickName = $values[PersonAttributeKeys::NICKNAME->value]->text_value ?? '';
        $firstName = $values[PersonAttributeKeys::FIRST_NAME->value]->text_value ?? '';
        $lastNamePrefix = $values[PersonAttributeKeys::LAST_NAME_PREFIX->value]->text_value ?? '';
        $lastName = $values[PersonAttributeKeys::LAST_NAME->value]->text_value ?? '';

        // Build the name from individual parts
        $nameParts = [];

        if (! empty($nickName)) {
            $nameParts[] = $nickName;
        } elseif (! empty($firstName)) {
            $nameParts[] = $firstName;
        }

        if (! empty($lastNamePrefix)) {
            $nameParts[] = $lastNamePrefix;
        }

        if (! empty($lastName)) {
            $nameParts[] = $lastName;
        }

        $fullName = implode(' ', $nameParts);

        // Update the name field directly in the database
        if (! empty($fullName)) {
            $person->update(['name' => $fullName]);
        } else {
            $person->update(['name' => '-']);
        }
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
        if ($leadPipelineId !== $lead->lead_pipeline_id) {
            $lead->update([
                'lead_pipeline_id'       => $leadPipelineId,
                'lead_pipeline_stage_id' => $leadPipelineStageId,
            ]);
        }
    }

    private function handleAttributeChanged(mixed $attribute, AttributeValue $attributeValue): void
    {
        if ($attribute->entity_type === 'persons') {

            if (in_array($attribute->code, $this->personAttributeKeysPartOfFullName)) {
                $person = Person::find($attributeValue->entity_id);
                $this->updateNameField($person);
            }
        } elseif ($attribute->entity_type === 'leads') {
            if ($attribute->code === LeadAttributeKeys::DEPARTMENT->value) {
                $lead = Lead::find($attributeValue->entity_id);
                if (! empty($attributeValue->integer_value)) {
                    $this->leadDepartmentUpdated($lead, AttributeOption::find($attributeValue->integer_value)->name);
                }
            }
        }
    }
}
