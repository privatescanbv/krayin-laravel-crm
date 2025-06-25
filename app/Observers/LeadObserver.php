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

        if ($lead->wasChanged('lead_pipeline_stage_id')) {
            $this->sendWebhook($lead, 'LeadObserver@created');
        }
    }

    /**
     * Handle the Lead "updated" event.
     */
    public function updated(Lead $lead): void
    {
        // Send webhook if stage has changed and the stage is actually different
        if ($lead->wasChanged('lead_pipeline_stage_id') && $lead->stage) {

            logger()->info('lead update', [
                'lead_id'            => $lead->id,
                'original'           => $lead->getOriginal('lead_pipeline_stage_id'),
                'new'                => $lead->lead_pipeline_stage_id,
                'wasChanged'         => $lead->wasChanged('lead_pipeline_stage_id'),
                'changed_attributes' => $lead->getChanges(),
                'dirty_attributes'   => $lead->getDirty(),
            ]);
            // 'stack_trace' => (new \Exception())->getTraceAsString(),
            $this->sendWebhook($lead, 'LeadObserver@updated');
        }
    }

    private function sendWebhook(Lead $lead, string $caller): void
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
            WebhookType::LEAD_PIPELINE_STAGE_CHANGE,
            $caller);
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
