<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\PipelineStage;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;

class ActivityQueueRegistry
{
    /**
     * Return all queue definitions as a flat array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return array_values($this->definitions());
    }

    /**
     * Get a single queue definition by key.
     */
    public function get(string $key): array
    {
        $definitions = $this->definitions();

        if (! isset($definitions[$key])) {
            throw new InvalidArgumentException("Unknown activity queue key [{$key}].");
        }

        return $definitions[$key];
    }

    /**
     * Apply filters for the given queue to the base query builder.
     */
    public function applyFilters(Builder $query, string $key, ?int $currentUserId = null): Builder
    {
        $definition = $this->get($key);

        /** @var callable $callback */
        $callback = $definition['apply'] ?? static fn () => null;

        $callback($query, $currentUserId);

        return $query;
    }

    /**
     * Get preferred sort configuration for a queue (datagrid `sort` payload).
     *
     * @return array{column:string,order:string}|null
     */
    public function getSort(string $key): ?array
    {
        $definition = $this->get($key);

        return $definition['sort'] ?? null;
    }

    /**
     * Internal queue definitions.
     *
     * Each queue uses a closure that mutates the given query builder.
     *
     * @return array<string, array<string, mixed>>
     */
    private function definitions(): array
    {
        // Helper sets for pipeline stages (IDs come from the central PipelineStage enum).
        $frontofficeLeadStageIds = PipelineStage::getFrontOfficeStageIds();
        $salesLeadStageIds = PipelineStage::getSalesOfficeStageIds();
        $midofficeOrderStageIds = PipelineStage::getMidofficeStageIds();
        $backofficeOrderStageIds = PipelineStage::getBackofficeStageIds();

        return [
            // Frontoffice – new incoming website requests.
            'frontoffice' => [
                'key'   => 'frontoffice',
                'label' => 'Frontoffice',
                'sort'  => [
                    'column' => 'created_at',
                    'order'  => 'desc',
                ],
                'apply' => static function (Builder $query) use ($frontofficeLeadStageIds): void {
                    $query
                        ->whereIn('leads.lead_pipeline_stage_id', $frontofficeLeadStageIds)
                        ->where('activities.is_done', false);
                },
            ],

            // Sales – leads currently in advising stages.
            'sales' => [
                'key'   => 'sales',
                'label' => 'Sales',
                // Use default urgency-based sort from the datagrid.
                'apply' => static function (Builder $query) use ($salesLeadStageIds): void {
                    $query
                        ->whereIn('leads.lead_pipeline_stage_id', $salesLeadStageIds)
                        ->where('activities.is_done', false);
                },
            ],

            // Midoffice – orders before execution.
            'midoffice' => [
                'key'   => 'midoffice',
                'label' => 'Midoffice',
                'sort'  => [
                    'column' => 'schedule_to',
                    'order'  => 'asc',
                ],
                'apply' => static function (Builder $query) use ($midofficeOrderStageIds): void {
                    $query
                        ->whereIn('orders.pipeline_stage_id', $midofficeOrderStageIds)
                        ->where('activities.is_done', false);
                },
            ],

            // Backoffice – orders after execution.
            'backoffice' => [
                'key'   => 'backoffice',
                'label' => 'Backoffice',
                'sort'  => [
                    'column' => 'schedule_to',
                    'order'  => 'asc',
                ],
                'apply' => static function (Builder $query) use ($backofficeOrderStageIds): void {
                    $query
                        ->whereIn('orders.pipeline_stage_id', $backofficeOrderStageIds)
                        ->where('activities.is_done', false);
                },
            ],

            // Onze openstaande taken – all internal tasks still open.
            'our-tasks' => [
                'key'   => 'our-tasks',
                'label' => 'Onze openstaande taken',
                'sort'  => [
                    'column' => 'schedule_to',
                    'order'  => 'asc',
                ],
                'apply' => static function (Builder $query): void {
                    $query
                        ->where('activities.type', ActivityType::TASK->value)
                        ->where('activities.is_done', false);
                },
            ],

            // Mijn openstaande taken – same as above, but only for current user.
            'my-tasks' => [
                'key'   => 'my-tasks',
                'label' => 'Mijn openstaande taken',
                'sort'  => [
                    'column' => 'schedule_to',
                    'order'  => 'asc',
                ],
                'apply' => static function (Builder $query, ?int $currentUserId): void {
                    $query
                        ->where('activities.type', ActivityType::TASK->value)
                        ->where('activities.is_done', false);

                    if ($currentUserId) {
                        $query->where('activities.user_id', $currentUserId);
                    }
                },
            ],

            // Onze telefoongesprekken – all calls, recognizable overdue via deadline styling.
            'our-calls' => [
                'key'   => 'our-calls',
                'label' => 'Onze telefoongesprekken',
                'sort'  => [
                    'column' => 'schedule_to',
                    'order'  => 'asc',
                ],
                'apply' => static function (Builder $query): void {
                    $query->where('activities.type', ActivityType::CALL->value);
                },
            ],

            // Upload bestanden door klanten – monitor file uploads from portal.
            'uploads' => [
                'key'   => 'uploads',
                'label' => 'Upload bestanden door patiënten',
                'sort'  => [
                    'column' => 'created_at',
                    'order'  => 'desc',
                ],
                'apply' => static function (Builder $query): void {
                    $query
                        ->where('activities.type', ActivityType::FILE->value)
                        ->where('activities.is_done', false);
                },
            ],
        ];
    }
}
