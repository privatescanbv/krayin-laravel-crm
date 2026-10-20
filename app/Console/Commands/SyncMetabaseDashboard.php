<?php

namespace App\Console\Commands;

use App\Services\Metabase\DashboardSyncService;
use App\Services\Metabase\MetabaseApiException;
use App\Services\Metabase\MetabaseClient;
use App\Services\Metabase\MetabaseDashboardRegistry;
use App\Services\Metabase\MetabaseEnvironments;
use App\Services\Metabase\MetabaseSyncException;
use App\Services\Metabase\SyncMapping;
use App\Services\Metabase\SyncReport;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Sleep;

class SyncMetabaseDashboard extends Command
{
    protected $signature = 'metabase:sync-dashboard
        {--source= : Source environment name (or full URL matching a configured environment)}
        {--target= : Target environment name (or full URL matching a configured environment)}
        {--dashboard=* : Numeric id of a dashboard in the source instance; repeat or comma-separate for several. Omit to sync all dashboards on the source}
        {--dry-run : Show what would change without writing to the target}
        {--force : Skip the confirmation prompt and continue on a version mismatch (for CI/CD)}
        {--sync-schema : Trigger a Metabase schema rescan on the target databases first, and retry while it catches up on new/changed columns (use after an analytics DB reset)}';

    protected $description = 'Synchroniseer Metabase-dashboards (incl. gekoppelde vragen) van de ene omgeving naar de andere via de HTTP API. Zonder --dashboard: alle dashboards van de bron.';

    public function handle(MetabaseEnvironments $environments, MetabaseDashboardRegistry $registry): int
    {
        $source = (string) $this->option('source');
        $target = (string) $this->option('target');

        if ($source === '' || $target === '') {
            $this->error('--source and --target are both required.');

            return self::FAILURE;
        }

        $dashboardIds = collect($this->option('dashboard'))
            ->flatMap(fn ($value) => explode(',', (string) $value))
            ->map(fn (string $id) => trim($id))
            ->filter(fn (string $id) => $id !== '')
            ->unique()
            ->values();

        if ($dashboardIds->contains(fn (string $id) => ! ctype_digit($id))) {
            $this->error('--dashboard must be a numeric Metabase dashboard id.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $failed = [];

        try {
            $sourceClient = $environments->resolve($source);
            $targetClient = $environments->resolve($target);

            if (rtrim($sourceClient->baseUrl(), '/') === rtrim($targetClient->baseUrl(), '/')) {
                $this->error('Source and target resolve to the same Metabase instance.');

                return self::FAILURE;
            }

            // id => name; without --dashboard every dashboard on the source, except Metabase's sample content.
            $dashboards = $dashboardIds->isEmpty()
                ? $this->allSourceDashboards($sourceClient)
                : $dashboardIds->mapWithKeys(fn (string $id) => [(int) $id => $sourceClient->getDashboard((int) $id)['name'] ?? '(no name)']);

            $this->line('');
            $this->line("Source:     {$sourceClient->label} ({$sourceClient->baseUrl()})");
            $this->line("Target:     {$targetClient->label} ({$targetClient->baseUrl()})");
            $this->line('Dashboards: '.($dashboards->isEmpty() ? '(none found on source)' : ''));
            foreach ($dashboards as $id => $name) {
                $this->line("  {$id} - {$name}");
            }
            $this->line('');

            if ($dashboards->isEmpty()) {
                return self::SUCCESS;
            }

            if ($dryRun) {
                $this->comment('Dry run — no changes will be written to the target.');
            }

            if (! $dryRun && ! $force && $this->input->isInteractive() && ! $this->confirm('Proceed with the sync?', false)) {
                $this->line('Aborted.');

                return self::SUCCESS;
            }

            $syncSchema = (bool) $this->option('sync-schema');

            if ($syncSchema) {
                $this->triggerTargetSchemaSync($targetClient);
            }
        } catch (MetabaseSyncException|MetabaseApiException $e) {
            $this->line('');
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // One mapping for all dashboards: a card shared by several dashboards is created once on the target.
        $mapping = new SyncMapping($sourceClient->label, $targetClient->label, readOnly: $dryRun);

        foreach ($dashboards as $dashboardId => $name) {
            $this->line('');
            $this->info("Dashboard {$dashboardId} - {$name}");

            try {
                $report = $this->syncWithRetry($sourceClient, $targetClient, $mapping, $dryRun, $force, $dashboardId, $syncSchema);
            } catch (MetabaseSyncException|MetabaseApiException $e) {
                $this->error($e->getMessage());
                $failed[$dashboardId] = $name;

                continue;
            }

            $this->renderReport($report);
            $this->renderEnvHint($registry, $mapping->get('dashboard', $dashboardId), $dashboardId, $targetClient->label);
        }

        if ($failed !== []) {
            $this->line('');
            $this->error('Failed dashboards: '.collect($failed)->map(fn ($name, $id) => "{$id} - {$name}")->implode(', '));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * All dashboards on the source as id => name, skipping those named in services.metabase.sync_exclude
     * and Metabase's bundled examples: dashboards in the sample "Examples" collection or built on the
     * Sample Database (e.g. "E-commerce Insights").
     */
    private function allSourceDashboards(MetabaseClient $source): Collection
    {
        $sampleDbIds = collect($source->listDatabases())
            ->filter(fn (array $db) => ! empty($db['is_sample']))
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $excluded = config('services.metabase.sync_exclude', []);

        return collect($source->listDashboards())
            ->reject(function (array $summary) use ($excluded) {
                $isExcluded = in_array($summary['name'] ?? null, $excluded, true);

                if ($isExcluded) {
                    $this->comment("Skipping dashboard {$summary['id']} - {$summary['name']}: excluded in services.metabase.sync_exclude.");
                }

                return $isExcluded;
            })
            ->reject(function (array $summary) use ($source, $sampleDbIds) {
                $dashboard = $source->getDashboard((int) $summary['id']);

                $isExample = ! empty($dashboard['collection']['is_sample'])
                    || collect($dashboard['dashcards'] ?? [])
                        ->contains(fn (array $dc) => $sampleDbIds->contains((int) ($dc['card']['database_id'] ?? 0)));

                if ($isExample) {
                    $this->comment("Skipping dashboard {$summary['id']} - {$summary['name']}: Metabase example.");
                }

                return $isExample;
            })
            ->mapWithKeys(fn (array $d) => [(int) $d['id'] => $d['name'] ?? '(no name)']);
    }

    /**
     * Metabase assigns its own ids on the target, so the CRM page for this
     * dashboard needs the target id in that environment's .env.
     */
    private function renderEnvHint(MetabaseDashboardRegistry $registry, ?int $targetId, int $sourceId, string $target): void
    {
        $page = collect($registry->pages())->firstWhere('dashboard_id', $sourceId);

        if ($page === null || $targetId === null) {
            return;
        }

        $var = 'METABASE_DASHBOARD_'.strtoupper(str_replace('-', '_', basename($page['path'])));

        $this->line('');
        $this->info("CRM page '{$page['name']}' on {$target} needs in its .env:");
        $this->line("  {$var}={$targetId}");
    }

    private function triggerTargetSchemaSync(MetabaseClient $target): void
    {
        $this->comment('Triggering Metabase schema rescan on target databases...');

        foreach ($target->listDatabases() as $db) {
            $id = (int) ($db['id'] ?? 0);

            if ($id > 0) {
                $target->syncDatabaseSchema($id);
            }
        }
    }

    /**
     * Retries the sync while Metabase's schema rescan (queued above) catches
     * up. Safe to retry: the sync service resolves every data-source
     * reference before writing anything, so a failed attempt never leaves
     * partial writes behind.
     */
    private function syncWithRetry(
        MetabaseClient $source,
        MetabaseClient $target,
        SyncMapping $mapping,
        bool $dryRun,
        bool $force,
        int $dashboardId,
        bool $waitForSchema,
    ): SyncReport {
        $delaysInSeconds = [3, 6, 12, 24];
        $attempt = 0;

        while (true) {
            try {
                return (new DashboardSyncService($source, $target, $mapping, $dryRun, ignoreVersionMismatch: $force))
                    ->sync($dashboardId);
            } catch (MetabaseSyncException $e) {
                if (! $waitForSchema || ! $this->isMissingDataSource($e) || ! isset($delaysInSeconds[$attempt])) {
                    throw $e;
                }

                $delay = $delaysInSeconds[$attempt++];
                $this->comment("Target schema not caught up yet ({$e->getMessage()}) — waiting {$delay}s and retrying...");
                Sleep::for($delay)->seconds();
            }
        }
    }

    private function isMissingDataSource(MetabaseSyncException $e): bool
    {
        return str_contains($e->getMessage(), 'does not exist in target')
            || str_contains($e->getMessage(), 'does not exist in the matching target table');
    }

    private function renderReport(SyncReport $report): void
    {
        $verb = $report->dryRun ? 'would be' : '';

        $rows = array_map(
            fn (array $item): array => [ucfirst($item['action']), $item['type'], $item['label']],
            $report->items(),
        );

        $this->line('');
        $this->table(['Action'.($verb ? " ({$verb})" : ''), 'Type', 'Name'], $rows ?: [['—', '—', 'nothing to do']]);

        $totals = $report->totals();
        $this->line('');
        $this->line(sprintf('Created: %d   Updated: %d   Skipped: %d', $totals['created'], $totals['updated'], $totals['skipped']));

        if ($report->warnings() !== []) {
            $this->line('');
            $this->warn('Warnings:');
            foreach ($report->warnings() as $warning) {
                $this->warn('  - '.$warning);
            }
        }
    }
}
