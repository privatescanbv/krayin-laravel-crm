<?php

namespace App\Observers;

use App\Enums\LeadPipelineStageDefaults;
use App\Enums\WebhookType;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Webkul\Attribute\Models\Attribute;
use Webkul\Attribute\Models\AttributeValue;
use Webkul\Lead\Models\Lead;

/**
 * Because some attributes of leads will be set by CRM workflows, we created @LeadWorkflowListener.
 * This Observer will be called before the CRM workflows.
 */
class LeadObserver
{
    /**
     * Create a new observer instance.
     */
    public function __construct(
        protected WebhookService $webhookService
    ) {}

    /**
     * Handle the Lead "updating" event.
     */
    public function updating(Lead $lead): void
    {
        Log::info('UPDATE lead', [
            'lead_id'   => $lead->id,
            'old_stage' => $lead->getOriginal('lead_pipeline_stage_id'),
            'new_stage' => $lead->lead_pipeline_stage_id,
        ]);

        // Check if the stage has changed
        if ($lead->isDirty('lead_pipeline_stage_id')) {
            $newStageCode = $lead->stage?->code;

            // If changing to 'klant_adviseren' stage
            if ($newStageCode === LeadPipelineStageDefaults::ADVICE->value) {
                // Check if person_id is set
                if (! $lead->person_id) {
                    Log::warning('Cannot update lead: missing person_id for advice stage', [
                        'lead_id' => $lead->id,
                    ]);
                    throw new HttpException(422, 'Contactpersoon is verplicht in de status "Klant adviseren"');
                }
            }
        }
    }

    /**
     * Handle the Lead "created" event.
     */
    public function created(Lead $lead): void
    {
        Log::info('CREATE lead', [
            'lead_id' => $lead->id,
            'stage'   => $lead->stage?->name,
        ]);

        $this->sendWebhook($lead);
    }

    /**
     * Handle the Lead "updated" event.
     */
    public function updated(Lead $lead): void
    {
        // Send webhook if stage has changed or department has changed
        if ($lead->isDirty('lead_pipeline_stage_id')) {
            $this->sendWebhook($lead);
        }
    }

    private function sendWebhook(Lead $lead): void
    {
        // Eager load the source relation
        $lead->load('source');

        $departmentValue = $this->getDepartmentValue($lead);

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
}
