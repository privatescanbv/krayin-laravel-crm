<?php

namespace App\Observers;

use App\Actions\Sales\SalesToLostAction;
use App\Enums\Departments;
use App\Enums\LostReason;
use App\Enums\PipelineDefaultKeys;
use App\Enums\WebhookType;
use App\Models\Department;
use App\Models\SalesLead;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Event;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

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
        private readonly ActivityRepository $activityRepository,
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

        $this->logFieldChanges($salesLead);
    }

    private function logFieldChanges(SalesLead $salesLead): void
    {
        $fields = [
            'name'              => 'Naam',
            'description'       => 'Omschrijving',
            'pipeline_stage_id' => 'Status',
            'user_id'           => 'Toegewezen aan',
            'department_id'     => 'Afdeling',
            'lost_reason'       => 'Reden verlies',
            'contact_person_id' => 'Contactpersoon',
        ];

        foreach ($fields as $field => $label) {
            if (! $salesLead->wasChanged($field)) {
                continue;
            }

            $oldRaw = $salesLead->getOriginal($field);
            $newRaw = $salesLead->getAttribute($field);

            [$oldLabel, $newLabel] = $this->resolveFieldLabels($field, $oldRaw, $newRaw);

            if (empty($oldLabel) && empty($newLabel)) {
                continue;
            }

            $this->activityRepository->createSystem([
                'title'         => "{$label} gewijzigd",
                'additional'    => [
                    'attribute' => $label,
                    'new'       => ['value' => $newRaw, 'label' => $newLabel ?: '-'],
                    'old'       => ['value' => $oldRaw, 'label' => $oldLabel ?: '-'],
                ],
                'user_id'       => auth()->id() ?? 1,
                'sales_lead_id' => $salesLead->id,
            ]);
        }
    }

    /**
     * @return array{string|null, string|null}
     */
    private function resolveFieldLabels(string $field, mixed $oldRaw, mixed $newRaw): array
    {
        return match ($field) {
            'pipeline_stage_id' => [
                Stage::find($oldRaw)?->name,
                Stage::find($newRaw)?->name,
            ],
            'user_id' => [
                User::find($oldRaw)?->name,
                User::find($newRaw)?->name,
            ],
            'department_id' => [
                Department::find($oldRaw)?->name,
                Department::find($newRaw)?->name,
            ],
            'contact_person_id' => [
                Person::find($oldRaw)?->name,
                Person::find($newRaw)?->name,
            ],
            'lost_reason' => [
                $oldRaw !== null ? (LostReason::tryFrom((string) $oldRaw)?->label() ?? (string) $oldRaw) : null,
                $newRaw instanceof LostReason
                    ? $newRaw->label()
                    : ($newRaw !== null ? (LostReason::tryFrom((string) $newRaw)?->label() ?? (string) $newRaw) : null),
            ],
            default => [(string) ($oldRaw ?? ''), (string) ($newRaw ?? '')],
        };
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
