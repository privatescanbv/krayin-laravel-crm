<?php

namespace App\Console\Commands;

use App\Services\Metabase\DashboardSyncService;
use App\Services\Metabase\MetabaseApiException;
use App\Services\Metabase\MetabaseClient;
use App\Services\Metabase\MetabaseEnvironments;
use App\Services\Metabase\MetabaseSyncException;
use App\Services\Metabase\SyncMapping;
use App\Services\Metabase\SyncReport;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;

class SyncMetabaseDashboard extends Command
{
    protected $signature = 'metabase:sync-dashboard
        {--source= : Source environment name (or full URL matching a configured environment)}
        {--target= : Target environment name (or full URL matching a configured environment)}
        {--dashboard= : Numeric id of the dashboard in the source instance}
        {--dry-run : Show what would change without writing to the target}
        {--force : Skip the confirmation prompt and continue on a version mismatch (for CI/CD)}
        {--sync-schema : Trigger a Metabase schema rescan on the target databases first, and retry while it catches up on new/changed columns (use after an analytics DB reset)}';

    protected $description = 'Synchroniseer een Metabase-dashboard (incl. gekoppelde vragen) van de ene omgeving naar de andere via de HTTP API.';

    public function handle(MetabaseEnvironments $environments): int
    {
        $source = (string) $this->option('source');
        $target = (string) $this->option('target');
        $dashboardOption = $this->option('dashboard');

        if ($source === '' || $target === '' || $dashboardOption === null || $dashboardOption === '') {
            $this->error('--source, --target and --dashboard are all required.');

            return self::FAILURE;
        }

        if (! ctype_digit((string) $dashboardOption)) {
            $this->error('--dashboard must be a numeric Metabase dashboard id.');

            return self::FAILURE;
        }

        $dashboardId = (int) $dashboardOption;
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        try {
            $sourceClient = $environments->resolve($source);
            $targetClient = $environments->resolve($target);

            if (rtrim($sourceClient->baseUrl(), '/') === rtrim($targetClient->baseUrl(), '/')) {
                $this->error('Source and target resolve to the same Metabase instance.');

                return self::FAILURE;
            }

            $dashboard = $sourceClient->getDashboard($dashboardId);

            $this->line('');
            $this->line("Source:    {$sourceClient->label} ({$sourceClient->baseUrl()})");
            $this->line("Target:    {$targetClient->label} ({$targetClient->baseUrl()})");
            $this->line("Dashboard: {$dashboardId} - ".($dashboard['name'] ?? '(no name)'));
            $this->line('');

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

            $mapping = new SyncMapping($sourceClient->label, $targetClient->label, readOnly: $dryRun);

            $report = $this->syncWithRetry($sourceClient, $targetClient, $mapping, $dryRun, $force, $dashboardId, $syncSchema);
        } catch (MetabaseSyncException|MetabaseApiException $e) {
            $this->line('');
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->renderReport($report);

        return self::SUCCESS;
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
