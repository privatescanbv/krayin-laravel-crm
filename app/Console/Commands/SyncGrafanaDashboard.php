<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Copies Grafana dashboards between environments over the HTTP API. A dashboard keeps its uid
 * everywhere; only datasource uids (matched by name, else by the single datasource of that type)
 * and the folder differ per environment. The target copy is overwritten.
 */
class SyncGrafanaDashboard extends Command
{
    protected $signature = 'grafana:sync-dashboard
        {--source= : Source environment name (services.grafana.environments)}
        {--target= : Target environment name (services.grafana.environments)}
        {--dashboard=* : Dashboard uid on the source; repeat or comma-separate for several. Omit to sync all dashboards on the source}
        {--dry-run : Show what would change without writing to the target}
        {--force : Skip the confirmation prompt (for CI/CD)}';

    protected $description = 'Synchroniseer Grafana-dashboards van de ene omgeving naar de andere via de HTTP API. Zonder --dashboard: alle dashboards van de bron.';

    public function handle(): int
    {
        $sourceName = (string) $this->option('source');
        $targetName = (string) $this->option('target');

        if ($sourceName === '' || $targetName === '') {
            $this->error('--source and --target are both required.');

            return self::FAILURE;
        }

        $environments = config('services.grafana.environments', []);

        foreach ([$sourceName, $targetName] as $name) {
            if (! isset($environments[$name])) {
                $known = implode(', ', array_keys($environments)) ?: '(none configured)';
                $this->error("Unknown Grafana environment '{$name}'. Known: {$known}. Set GRAFANA_".strtoupper($name).'_URL.');

                return self::FAILURE;
            }
        }

        if (rtrim($environments[$sourceName]['url'], '/') === rtrim($environments[$targetName]['url'], '/')) {
            $this->error('Source and target resolve to the same Grafana instance.');

            return self::FAILURE;
        }

        $source = $this->client($environments[$sourceName]);
        $target = $this->client($environments[$targetName]);
        $dryRun = (bool) $this->option('dry-run');
        $failed = [];

        try {
            $uids = collect($this->option('dashboard'))
                ->flatMap(fn ($value) => explode(',', (string) $value))
                ->map(fn (string $uid) => trim($uid))
                ->filter()
                ->unique()
                ->values();

            if ($uids->isEmpty()) {
                $uids = collect($source->get('/api/search', ['type' => 'dash-db', 'limit' => 5000])->throw()->json())->pluck('uid');
            }

            $datasourceMap = $this->datasourceMap($source, $target);
        } catch (RequestException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('');
        $this->line("Source:     {$sourceName} ({$environments[$sourceName]['url']})");
        $this->line("Target:     {$targetName} ({$environments[$targetName]['url']})");
        $this->line('Dashboards: '.($uids->isEmpty() ? '(none found on source)' : $uids->implode(', ')));
        $this->line('');

        if ($uids->isEmpty()) {
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->comment('Dry run — no changes will be written to the target.');
        } elseif (! $this->option('force') && $this->input->isInteractive() && ! $this->confirm('Proceed with the sync? Target dashboards are overwritten.', false)) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        foreach ($uids as $uid) {
            try {
                $this->syncDashboard($source, $target, $uid, $datasourceMap, $sourceName, $dryRun);
            } catch (RequestException|RuntimeException $e) {
                $this->error("  {$e->getMessage()}");
                $failed[] = $uid;
            }
        }

        if ($failed !== []) {
            $this->line('');
            $this->error('Failed dashboards: '.implode(', ', $failed));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<string, string|null>  $datasourceMap  source uid => target uid (null when unmatched)
     */
    private function syncDashboard(PendingRequest $source, PendingRequest $target, string $uid, Collection $datasourceMap, string $sourceName, bool $dryRun): void
    {
        $payload = $source->get("/api/dashboards/uid/{$uid}")->throw()->json();
        $dashboard = $payload['dashboard'];
        $this->info("Dashboard {$uid} - {$dashboard['title']}");

        $used = $this->datasourceUids($dashboard)->filter(fn (string $dsUid) => $datasourceMap->has($dsUid));
        $unmatched = $used->filter(fn (string $dsUid) => $datasourceMap[$dsUid] === null);

        if ($unmatched->isNotEmpty()) {
            throw new RuntimeException('No matching datasource on the target for: '.$unmatched->implode(', '));
        }

        foreach ($used as $dsUid) {
            $this->line("  datasource {$dsUid} → {$datasourceMap[$dsUid]}");
        }

        $dashboard = $this->remapDatasources($dashboard, $datasourceMap);
        $dashboard['id'] = null;

        $existing = $target->get("/api/dashboards/uid/{$uid}")->throwIf(fn ($response) => $response->failed() && $response->status() !== 404);
        $this->line($existing->status() === 404
            ? '  will be created'
            : "  will overwrite target version {$existing->json('dashboard.version')} (updated {$existing->json('meta.updated')} by {$existing->json('meta.updatedBy')})");

        $folderUid = $this->targetFolderUid($target, $payload['meta'] ?? [], $dryRun);

        if ($dryRun) {
            return;
        }

        $target->post('/api/dashboards/db', [
            'dashboard' => $dashboard,
            'folderUid' => $folderUid,
            'overwrite' => true,
            'message'   => "Sync from {$sourceName}",
        ])->throw();

        $this->line('  synced');
    }

    /**
     * Source datasource uid => target uid: same name first, else the only target datasource of that type.
     *
     * @return Collection<string, string|null>
     */
    private function datasourceMap(PendingRequest $source, PendingRequest $target): Collection
    {
        $targetDatasources = collect($target->get('/api/datasources')->throw()->json());

        return collect($source->get('/api/datasources')->throw()->json())->mapWithKeys(function (array $ds) use ($targetDatasources) {
            $byType = $targetDatasources->where('type', $ds['type']);
            $match = $targetDatasources->firstWhere('name', $ds['name']) ?? ($byType->count() === 1 ? $byType->first() : null);

            return [$ds['uid'] => $match['uid'] ?? null];
        });
    }

    /** @return Collection<int, string> */
    private function datasourceUids(array $node): Collection
    {
        $uids = collect();

        foreach ($node as $key => $value) {
            if ($key === 'datasource' && is_array($value) && isset($value['uid'])) {
                $uids->push((string) $value['uid']);
            } elseif (is_array($value)) {
                $uids = $uids->merge($this->datasourceUids($value));
            }
        }

        return $uids->unique()->values();
    }

    private function remapDatasources(array $node, Collection $datasourceMap): array
    {
        foreach ($node as $key => $value) {
            if ($key === 'datasource' && is_array($value) && isset($value['uid']) && $datasourceMap->get($value['uid'])) {
                $node[$key]['uid'] = $datasourceMap[$value['uid']];
            } elseif (is_array($value)) {
                $node[$key] = $this->remapDatasources($value, $datasourceMap);
            }
        }

        return $node;
    }

    /**
     * Same folder title on the target; created (with the source uid) when missing. '' = General.
     */
    private function targetFolderUid(PendingRequest $target, array $meta, bool $dryRun): string
    {
        if (empty($meta['folderUid'])) {
            return '';
        }

        $title = $meta['folderTitle'];
        $existing = collect($target->get('/api/folders', ['limit' => 1000])->throw()->json())->firstWhere('title', $title);

        if ($existing) {
            return $existing['uid'];
        }

        $this->line("  folder '{$title}' will be created");

        return $dryRun ? '' : $target->post('/api/folders', ['uid' => $meta['folderUid'], 'title' => $title])->throw()->json('uid');
    }

    private function client(array $environment): PendingRequest
    {
        $request = Http::baseUrl(rtrim($environment['url'], '/'))
            ->acceptJson()
            ->timeout((int) config('services.grafana.timeout', 30));

        return empty($environment['token'])
            ? $request->withBasicAuth((string) $environment['user'], (string) $environment['password'])
            : $request->withToken($environment['token']);
    }
}
