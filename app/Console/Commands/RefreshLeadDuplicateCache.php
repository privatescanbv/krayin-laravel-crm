<?php

namespace App\Console\Commands;

use App\Services\LeadDuplicateCacheService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshLeadDuplicateCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:refresh-duplicate-cache
                          {--full : Rebuild cache for all leads}
                          {--stats : Show cache statistics}
                          {--clear : Clear all duplicate caches}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh the lead duplicate detection cache';

    /**
     * Create a new command instance.
     */
    public function __construct(
        private LeadDuplicateCacheService $cacheService
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
        $this->info('Lead Duplicate Cache Statistics');
        $this->line('================================');

        $stats = $this->cacheService->getCacheStats();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Leads', $stats['total_leads']],
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
            $this->info('Clearing all lead duplicate caches...');
            $this->cacheService->clearAllCache();
            $this->info('✅ All caches cleared successfully');
        }
    }

    /**
     * Full cache rebuild.
     */
    private function fullRebuild(): void
    {
        $this->info('Starting full lead duplicate cache rebuild...');
        $this->info('This may take several minutes depending on the number of leads.');

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

        // For incremental refresh, we'll rebuild cache for recently modified leads
        // This is more efficient than full rebuild for cron jobs
        $this->incrementalCacheRefresh();

        $this->info('✅ Incremental cache refresh completed successfully');
    }

    /**
     * Refresh cache for recently modified leads.
     */
    private function incrementalCacheRefresh(): void
    {
        // Get leads modified in the last 24 hours
        $recentLeads = DB::table('leads')
            ->where('updated_at', '>=', now()->subDay())
            ->pluck('id');

        if ($recentLeads->isEmpty()) {
            $this->info('No recently modified leads found.');

            return;
        }

        $this->info("Refreshing cache for {$recentLeads->count()} recently modified leads...");

        $progressBar = $this->output->createProgressBar($recentLeads->count());
        $progressBar->start();

        foreach ($recentLeads as $leadId) {
            try {
                $this->cacheService->refreshLeadCache($leadId);
                $progressBar->advance();
            } catch (Exception $e) {
                $this->warn("Failed to refresh cache for lead {$leadId}: ".$e->getMessage());
            }
        }

        $progressBar->finish();
        $this->newLine();
    }
}
