<?php

namespace App\Services\Metabase;

/**
 * Orchestrates syncing one dashboard (and the questions it uses) from a source
 * Metabase instance to a target instance over the HTTP API.
 *
 * Guarantees:
 * - Idempotent: re-running updates the mapped target objects, never duplicates.
 * - No partial writes on a missing data-source: database/table/field references
 *   are resolved in a pre-flight pass; if any is missing in the target the run
 *   aborts before creating or updating anything.
 * - Dry-run performs only reads.
 *
 * Known ceilings (documented, acceptable for v1):
 * - New cards/dashboards land in the target's root collection.
 * - Native SQL: only the database link is retargeted; table/column names in the
 *   SQL text are copied verbatim (a warning is emitted per card).
 * - `visualization_settings.column_settings` keys that embed a numeric field ref
 *   are copied verbatim (a warning is emitted).
 * - Cards referencing other saved questions (`card__N`) are supported only when
 *   that question is also on this dashboard or was synced earlier.
 */
class DashboardSyncService
{
    private DataSourceResolver $resolver;

    /** @var array<int, array<string, mixed>> source card id => card payload */
    private array $sourceCards = [];

    /** @var array<int, int> source card id => target card id */
    private array $cardMap = [];

    private int $placeholderId = 0;

    public function __construct(
        private readonly MetabaseClient $source,
        private readonly MetabaseClient $target,
        private readonly SyncMapping $mapping,
        private readonly bool $dryRun,
        private readonly bool $ignoreVersionMismatch = false,
    ) {
        $this->resolver = new DataSourceResolver($source, $target);
    }

    public function sync(int $dashboardId): SyncReport
    {
        $report = new SyncReport($this->dryRun);

        $this->assertCompatibleVersions($report);

        $dashboard = $this->source->getDashboard($dashboardId);
        $this->loadSourceCards($dashboard);

        $order = $this->orderCardsByDependency();

        // Pre-flight: resolve every data-source reference. Throws before any write.
        $this->preflightDataSources($order);

        // Phase 1 — cards.
        foreach ($order as $sourceCardId) {
            $this->syncCard($this->sourceCards[$sourceCardId], $report);
        }

        // Phase 2 — dashboard container + layout.
        $this->syncDashboard($dashboardId, $dashboard, $report);

        return $report;
    }

    /* ---- version --------------------------------------------------------- */

    private function assertCompatibleVersions(SyncReport $report): void
    {
        $sourceVersion = $this->safeVersion($this->source);
        $targetVersion = $this->safeVersion($this->target);

        if ($sourceVersion === null || $targetVersion === null) {
            $report->warn('could not determine Metabase version on both instances; version compatibility not checked');

            return;
        }

        $sourceLine = $this->majorLine($sourceVersion);
        $targetLine = $this->majorLine($targetVersion);

        if ($sourceLine !== null && $targetLine !== null && $sourceLine !== $targetLine) {
            $message = "Metabase version mismatch: source is {$sourceVersion}, target is {$targetVersion}.";

            if (! $this->ignoreVersionMismatch) {
                throw new MetabaseSyncException($message.' Re-run with --force to sync anyway.');
            }

            $report->warn($message.' Continuing because --force was given.');
        }
    }

    private function safeVersion(MetabaseClient $client): ?string
    {
        try {
            $version = trim($client->version());
        } catch (MetabaseApiException) {
            return null;
        }

        return $version === '' ? null : $version;
    }

    /** "v0.49.6" / "v1.49.6" => 49 */
    private function majorLine(string $version): ?int
    {
        if (preg_match('/^v?\d+\.(\d+)/', $version, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /* ---- cards ---------------------------------------------------------- */

    private function loadSourceCards(array $dashboard): void
    {
        foreach ($dashboard['dashcards'] ?? [] as $dashcard) {
            foreach ($this->cardIdsInDashcard($dashcard) as $cardId) {
                $this->sourceCards[$cardId] ??= $this->source->getCard($cardId);
            }
        }
    }

    /** @return list<int> */
    private function cardIdsInDashcard(array $dashcard): array
    {
        $ids = [];

        if (! empty($dashcard['card_id'])) {
            $ids[] = (int) $dashcard['card_id'];
        }

        foreach ($dashcard['series'] ?? [] as $series) {
            if (! empty($series['id'])) {
                $ids[] = (int) $series['id'];
            }
        }

        return $ids;
    }

    /**
     * Topologically order the dashboard's cards so a card referenced by another
     * (via `card__N`) is synced first. External unmapped references abort.
     *
     * @return list<int>
     */
    private function orderCardsByDependency(): array
    {
        $ordered = [];
        $visiting = [];

        $visit = function (int $cardId) use (&$visit, &$ordered, &$visiting): void {
            if (isset($this->cardMap[$cardId]) || in_array($cardId, $ordered, true)) {
                return;
            }

            if (isset($visiting[$cardId])) {
                return; // cycle — let Metabase reject it
            }

            $visiting[$cardId] = true;

            foreach ($this->referencedCardIds($this->sourceCards[$cardId]['dataset_query'] ?? []) as $dependency) {
                if (isset($this->sourceCards[$dependency])) {
                    $visit($dependency);

                    continue;
                }

                if ($this->mapping->get('card', $dependency) === null) {
                    throw new MetabaseSyncException(
                        "Card {$cardId} references saved question {$dependency}, which is not on this dashboard and was not synced earlier. Sync that question first."
                    );
                }
            }

            unset($visiting[$cardId]);
            $ordered[] = $cardId;
        };

        foreach (array_keys($this->sourceCards) as $cardId) {
            $visit($cardId);
        }

        return $ordered;
    }

    /** @return list<int> */
    private function referencedCardIds(array $datasetQuery): array
    {
        $ids = [];

        array_walk_recursive($datasetQuery, function ($value) use (&$ids): void {
            if (is_string($value) && preg_match('/^card__(\d+)$/', $value, $m)) {
                $ids[] = (int) $m[1];
            }
        });

        return array_values(array_unique($ids));
    }

    /** @param list<int> $order */
    private function preflightDataSources(array $order): void
    {
        $translator = new QueryTranslator($this->resolver, fn (int $id): int => $id);

        foreach ($order as $cardId) {
            $translator->translate($this->sourceCards[$cardId]['dataset_query'] ?? []);
        }
    }

    private function syncCard(array $card, SyncReport $report): void
    {
        $sourceId = (int) $card['id'];
        $name = (string) ($card['name'] ?? "card {$sourceId}");

        $translator = new QueryTranslator($this->resolver, fn (int $id): int => $this->resolveCardId($id));
        $translatedQuery = $translator->translate($card['dataset_query'] ?? []);

        foreach ($translator->warnings() as $warning) {
            $report->warn("card '{$name}': {$warning}");
        }

        $payload = [
            'name'                   => $name,
            'description'            => $card['description'] ?? null,
            'display'                => $card['display'] ?? 'table',
            'visualization_settings' => $this->asMap($card['visualization_settings'] ?? []),
            'dataset_query'          => $translatedQuery,
            'collection_id'          => null,
        ];

        if (array_key_exists('parameters', $card)) {
            $payload['parameters'] = $card['parameters'] ?? [];
        }

        $targetId = $this->mapping->get('card', $sourceId);

        if ($targetId !== null) {
            if (! $this->dryRun) {
                $this->target->updateCard($targetId, $payload);
            }
            $report->updated('card', $name);
        } else {
            if ($this->dryRun) {
                $targetId = --$this->placeholderId;
            } else {
                $created = $this->target->createCard($payload);
                $targetId = (int) $created['id'];
                $this->mapping->put('card', $sourceId, $targetId);
            }
            $report->created('card', $name);
        }

        $this->cardMap[$sourceId] = $targetId;
    }

    private function resolveCardId(int $sourceCardId): int
    {
        return $this->cardMap[$sourceCardId]
            ?? $this->mapping->get('card', $sourceCardId)
            ?? throw new MetabaseSyncException("Referenced card {$sourceCardId} has not been synced yet.");
    }

    /* ---- dashboard ----------------------------------------------------- */

    private function syncDashboard(int $sourceDashboardId, array $dashboard, SyncReport $report): void
    {
        $name = (string) ($dashboard['name'] ?? "dashboard {$sourceDashboardId}");
        $targetDashboardId = $this->mapping->get('dashboard', $sourceDashboardId);
        $targetDashboard = ['dashcards' => [], 'tabs' => []];

        if ($targetDashboardId !== null) {
            if (! $this->dryRun) {
                $targetDashboard = $this->target->getDashboard($targetDashboardId);
            }
            $report->updated('dashboard', $name);
        } else {
            $containerPayload = [
                'name'          => $name,
                'description'   => $dashboard['description'] ?? null,
                'collection_id' => null,
            ];

            if ($this->dryRun) {
                $targetDashboardId = --$this->placeholderId;
            } else {
                $created = $this->target->createDashboard($containerPayload);
                $targetDashboardId = (int) $created['id'];
                $this->mapping->put('dashboard', $sourceDashboardId, $targetDashboardId);
            }
            $report->created('dashboard', $name);
        }

        $tabsPayload = $this->buildTabs($dashboard, $targetDashboard, $tabMap);
        $dashcardsPayload = $this->buildDashcards($dashboard, $targetDashboard, $tabMap, $report);

        $payload = [
            'name'        => $name,
            'description' => $dashboard['description'] ?? null,
            'parameters'  => $this->translateParameters($dashboard['parameters'] ?? []),
            'dashcards'   => $dashcardsPayload,
        ];

        if ($tabsPayload !== []) {
            $payload['tabs'] = $tabsPayload;
        }

        if ($this->dryRun) {
            return;
        }

        $result = $this->target->updateDashboard($targetDashboardId, $payload);

        $this->reconcileMappings($dashboard, $result);
    }

    /**
     * @param  array<int, int>|null  $tabMap  source tab id => target tab id (by ref)
     * @return list<array<string, mixed>>
     */
    private function buildTabs(array $dashboard, array $targetDashboard, ?array &$tabMap): array
    {
        $tabMap = [];
        $payload = [];

        $existingByName = [];
        foreach ($targetDashboard['tabs'] ?? [] as $tab) {
            $existingByName[mb_strtolower((string) ($tab['name'] ?? ''))] = (int) $tab['id'];
        }

        foreach ($dashboard['tabs'] ?? [] as $tab) {
            $sourceTabId = (int) $tab['id'];
            $targetTabId = $this->mapping->get('dashtab', $sourceTabId)
                ?? $existingByName[mb_strtolower((string) ($tab['name'] ?? ''))]
                ?? --$this->placeholderId;

            $tabMap[$sourceTabId] = $targetTabId;

            $payload[] = [
                'id'       => $targetTabId,
                'name'     => (string) ($tab['name'] ?? 'Tab'),
                'position' => $tab['position'] ?? count($payload),
            ];
        }

        return $payload;
    }

    /**
     * @param  array<int, int>  $tabMap
     * @return list<array<string, mixed>>
     */
    private function buildDashcards(array $dashboard, array $targetDashboard, array $tabMap, SyncReport $report): array
    {
        $dashcardMap = [];
        foreach ($dashboard['dashcards'] ?? [] as $dc) {
            $mapped = $this->mapping->get('dashcard', (int) $dc['id']);
            if ($mapped !== null) {
                $dashcardMap[(int) $dc['id']] = $mapped;
            }
        }

        // Preserve target dashcards we don't own (out of scope: never delete target-only cards).
        $ownedTargetIds = array_values($dashcardMap);
        $preserved = array_values(array_filter(
            $targetDashboard['dashcards'] ?? [],
            fn (array $dc): bool => ! in_array((int) $dc['id'], $ownedTargetIds, true),
        ));

        $synced = [];

        foreach ($dashboard['dashcards'] ?? [] as $dc) {
            $sourceCardId = isset($dc['card_id']) ? (int) $dc['card_id'] : null;

            $synced[] = array_filter([
                'id'                     => $dashcardMap[(int) $dc['id']] ?? --$this->placeholderId,
                'card_id'                => $sourceCardId !== null ? $this->cardMap[$sourceCardId] : null,
                'row'                    => $dc['row'] ?? 0,
                'col'                    => $dc['col'] ?? 0,
                'size_x'                 => $dc['size_x'] ?? 4,
                'size_y'                 => $dc['size_y'] ?? 4,
                'series'                 => $this->translateSeries($dc['series'] ?? []),
                'parameter_mappings'     => $this->translateParameterMappings($dc['parameter_mappings'] ?? []),
                'visualization_settings' => $this->asMap($this->translateVisualizationSettings($dc['visualization_settings'] ?? [], $report)),
                'dashboard_tab_id'       => isset($dc['dashboard_tab_id']) ? ($tabMap[(int) $dc['dashboard_tab_id']] ?? null) : null,
            ], fn ($value) => $value !== null);
        }

        return array_merge($preserved, $synced);
    }

    /** @return list<array{id: int}> */
    private function translateSeries(array $series): array
    {
        $out = [];

        foreach ($series as $entry) {
            if (! empty($entry['id']) && isset($this->cardMap[(int) $entry['id']])) {
                $out[] = ['id' => $this->cardMap[(int) $entry['id']]];
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function translateParameterMappings(array $mappings): array
    {
        $out = [];

        foreach ($mappings as $mapping) {
            if (isset($mapping['card_id'])) {
                $mapping['card_id'] = $this->cardMap[(int) $mapping['card_id']] ?? $mapping['card_id'];
            }

            if (isset($mapping['target'])) {
                $mapping['target'] = $this->translateFieldRefs($mapping['target']);
            }

            $out[] = $mapping;
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function translateParameters(array $parameters): array
    {
        foreach ($parameters as $i => $parameter) {
            if (isset($parameter['values_source_config']['card_id'])) {
                $sourceCardId = (int) $parameter['values_source_config']['card_id'];
                $parameters[$i]['values_source_config']['card_id'] = $this->cardMap[$sourceCardId]
                    ?? $this->mapping->get('card', $sourceCardId)
                    ?? $parameter['values_source_config']['card_id'];
            }

            if (isset($parameter['values_source_config']['value_field'])) {
                $parameters[$i]['values_source_config']['value_field'] = $this->translateFieldRefs($parameter['values_source_config']['value_field']);
            }
        }

        return array_values($parameters);
    }

    /** @return array<string, mixed> */
    private function translateVisualizationSettings(array $settings, SyncReport $report): array
    {
        if (isset($settings['click_behavior']) && is_array($settings['click_behavior'])) {
            $settings['click_behavior'] = $this->translateClickBehavior($settings['click_behavior']);
        }

        foreach (array_keys($settings['column_settings'] ?? []) as $key) {
            if (is_string($key) && preg_match('/\[\s*"field"\s*,\s*\d+/', $key)) {
                $report->warn('a column_settings key references a numeric field id and was copied verbatim; check column formatting on the target');
                break;
            }
        }

        return $settings;
    }

    /** @param array<string, mixed> $behavior @return array<string, mixed> */
    private function translateClickBehavior(array $behavior): array
    {
        $type = $behavior['linkType'] ?? null;

        if (isset($behavior['targetId'])) {
            $sourceId = (int) $behavior['targetId'];

            if ($type === 'question') {
                $behavior['targetId'] = $this->cardMap[$sourceId] ?? $this->mapping->get('card', $sourceId) ?? $behavior['targetId'];
            } elseif ($type === 'dashboard') {
                $behavior['targetId'] = $this->mapping->get('dashboard', $sourceId) ?? $behavior['targetId'];
            }
        }

        if (isset($behavior['parameterMapping']) && is_array($behavior['parameterMapping'])) {
            foreach ($behavior['parameterMapping'] as $k => $pm) {
                if (isset($pm['target'])) {
                    $behavior['parameterMapping'][$k]['target'] = $this->translateFieldRefs($pm['target']);
                }
            }
        }

        return $behavior;
    }

    /** Recursively rewrite ["field", <int>, opts] refs inside an arbitrary structure. */
    private function translateFieldRefs(mixed $node): mixed
    {
        if (! is_array($node)) {
            return $node;
        }

        if (array_is_list($node) && ($node[0] ?? null) === 'field' && isset($node[1]) && is_int($node[1])) {
            $node[1] = $this->resolver->fieldId($node[1]);
            if (isset($node[2]) && is_array($node[2])) {
                $node[2] = $this->translateFieldRefs($node[2]);
            }

            return $node;
        }

        foreach ($node as $key => $value) {
            $node[$key] = $this->translateFieldRefs($value);
        }

        return $node;
    }

    /* ---- mapping reconciliation --------------------------------------- */

    private function reconcileMappings(array $sourceDashboard, array $targetDashboard): void
    {
        // Tabs: match by name.
        $targetTabByName = [];
        foreach ($targetDashboard['tabs'] ?? [] as $tab) {
            $targetTabByName[mb_strtolower((string) ($tab['name'] ?? ''))] = (int) $tab['id'];
        }
        foreach ($sourceDashboard['tabs'] ?? [] as $tab) {
            $targetTabId = $targetTabByName[mb_strtolower((string) ($tab['name'] ?? ''))] ?? null;
            if ($targetTabId !== null) {
                $this->mapping->put('dashtab', (int) $tab['id'], $targetTabId);
            }
        }

        // Dashcards: match by (target card_id, row, col).
        $index = [];
        foreach ($targetDashboard['dashcards'] ?? [] as $dc) {
            $index[$this->dashcardKey((int) ($dc['card_id'] ?? 0), (int) ($dc['row'] ?? 0), (int) ($dc['col'] ?? 0))] = (int) $dc['id'];
        }

        foreach ($sourceDashboard['dashcards'] ?? [] as $dc) {
            $sourceCardId = isset($dc['card_id']) ? (int) $dc['card_id'] : 0;
            $targetCardId = $this->cardMap[$sourceCardId] ?? 0;
            $key = $this->dashcardKey($targetCardId, (int) ($dc['row'] ?? 0), (int) ($dc['col'] ?? 0));

            if (isset($index[$key])) {
                $this->mapping->put('dashcard', (int) $dc['id'], $index[$key]);
            }
        }
    }

    private function dashcardKey(int $cardId, int $row, int $col): string
    {
        return $cardId.':'.$row.':'.$col;
    }

    /**
     * Metabase requires a JSON object for settings blobs; an empty PHP array
     * serialises as `[]` and is rejected ("Value must be a map").
     */
    private function asMap(array $value): array|\stdClass
    {
        return $value === [] ? new \stdClass : $value;
    }
}
