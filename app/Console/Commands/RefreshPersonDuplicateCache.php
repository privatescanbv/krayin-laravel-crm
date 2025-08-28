<?php

namespace App\Console\Commands;

use App\Services\PersonDuplicateCacheService;
use Exception;
use Illuminate\Console\Command;

class RefreshPersonDuplicateCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'persons:refresh-duplicate-cache
                          {--full : Rebuild cache for all persons}
                          {--stats : Show cache statistics}
                          {--clear : Clear all duplicate caches}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh the person duplicate detection cache';

    /**
     * Create a new command instance.
     */
    public function __construct(
        private PersonDuplicateCacheService $cacheService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            if ($this->option('stats')) {
                $this->showStats();

                return Command::SUCCESS;
            }

            if ($this->option('clear')) {
                $this->clearCache();

                return Command::SUCCESS;
            }

            if ($this->option('full')) {
                $this->fullRebuild();
            } else {
                $this->incrementalRefresh();
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('Command failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Show cache statistics.
     */
    private function showStats(): void
    {
        $this->info('Person Duplicate Cache Statistics');
        $this->line('==================================');

        $stats = $this->cacheService->getCacheStats();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Persons', $stats['total_persons']],
                ['Cache Backend', $stats['cache_backend']],
                ['Cache TTL', $stats['cache_ttl_hours'].' hours'],
            ]
        );
    }

    /**
     * Clear all caches.
     */
    private function clearCache(): void
    {
        if ($this->confirm('Are you sure you want to clear all duplicate caches?')) {
            $this->info('Clearing all person duplicate caches...');
            $this->cacheService->clearAllCache();
            $this->info('✅ All caches cleared successfully');
        }
    }

    /**
     * Full cache rebuild.
     */
    private function fullRebuild(): void
    {
        $this->info('Starting full person duplicate cache rebuild...');
        $this->info('This may take several minutes depending on the number of persons.');

        $progressBar = $this->output->createProgressBar();
        $progressBar->start();

        $this->cacheService->clearAllCache();

        $progressBar->finish();
        $this->newLine();
        $this->info('✅ Cache cleared successfully (will rebuild on demand)');
    }

    /**
     * Incremental refresh (default behavior for cron).
     */
    private function incrementalRefresh(): void
    {
        $this->info('Performing incremental cache refresh...');

        // For incremental refresh, we'll rebuild cache for recently modified persons
        // This is more efficient than full rebuild for cron jobs
        $this->incrementalCacheRefresh();

        $this->info('✅ Incremental cache refresh completed successfully');
    }

    /**
     * Refresh cache for recently modified persons.
     */
    private function incrementalCacheRefresh(): void
    {
        // Get persons modified in the last 24 hours
        $recentPersons = \DB::table('persons')
            ->where('updated_at', '>=', now()->subDay())
            ->pluck('id');

        if ($recentPersons->isEmpty()) {
            $this->info('No recently modified persons found.');

            return;
        }

        $this->info("Refreshing cache for {$recentPersons->count()} recently modified persons...");

        $progressBar = $this->output->createProgressBar($recentPersons->count());
        $progressBar->start();

        foreach ($recentPersons as $personId) {
            try {
                $this->cacheService->refreshPersonCache($personId);
                $progressBar->advance();
            } catch (Exception $e) {
                $this->warn("Failed to refresh cache for person {$personId}: ".$e->getMessage());
            }
        }

        $progressBar->finish();
        $this->newLine();
    }
}