<?php

namespace App\Console\Commands;

use App\Services\LeadDuplicateCacheService;
use App\Services\PersonDuplicateCacheService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Used in cronjob to refresh duplicate detection caches every hour
 */
class RefreshDuplicateCache extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'duplicates:refresh-cache
                          {--full : Rebuild cache for all entities}
                          {--stats : Show cache statistics}
                          {--clear : Clear all duplicate caches}';

    /**
     * The console command description.
     */
    protected $description = 'Refresh duplicate detection caches for leads and persons';

    public function handle(): int
    {
        try {
            $doFull = (bool) $this->option('full');
            $doStats = (bool) $this->option('stats');
            $doClear = (bool) $this->option('clear');

            $personCache = app(PersonDuplicateCacheService::class);
            $leadCache = app(LeadDuplicateCacheService::class);

            if ($doStats) {
                $this->info('== Persons duplicate cache stats ==');
                $pStats = $personCache->getCacheStats();
                $this->table(['Metric', 'Value'], [
                    ['Total Persons', $pStats['total_persons']],
                    ['Cached Count', $pStats['cached_count'] ?? '-'],
                    ['Coverage %', $pStats['coverage_pct'] ?? '-'],
                    ['Cache Backend', $pStats['cache_backend']],
                    ['TTL (hours)', $pStats['cache_ttl_hours']],
                ]);

                $this->line('');
                $this->info('== Leads duplicate cache stats ==');
                $lStats = $leadCache->getCacheStats();
                $this->table(['Metric', 'Value'], [
                    ['Total Leads', $lStats['total_leads']],
                    ['Cache Backend', $lStats['cache_backend']],
                    ['TTL (hours)', $lStats['cache_ttl_hours']],
                ]);

                return Command::SUCCESS;
            }

            if ($doClear) {
                $this->info('Clearing all person duplicate caches...');
                $personCache->clearAllCache();
                $this->info('Clearing all lead duplicate caches...');
                $leadCache->clearAllCache();
                $this->info('✅ All caches cleared');

                return Command::SUCCESS;
            }

            if ($doFull) {
                // Full rebuild persons
                $this->info('Full rebuild: persons duplicate cache');
                $totalPersons = DB::table('persons')->count();
                if ($totalPersons > 0) {
                    $bar = $this->output->createProgressBar($totalPersons);
                    $bar->start();
                    DB::table('persons')->select('id')->orderBy('id')->chunk(1000, function ($rows) use ($personCache, $bar) {
                        foreach ($rows as $row) {
                            $personCache->refreshPersonCache((int) $row->id);
                            $bar->advance();
                        }
                    });
                    $bar->finish();
                    $this->newLine();
                }

                // Full rebuild leads
                $this->info('Full rebuild: leads duplicate cache');
                $totalLeads = DB::table('leads')->count();
                if ($totalLeads > 0) {
                    $bar = $this->output->createProgressBar($totalLeads);
                    $bar->start();
                    DB::table('leads')->select('id')->orderBy('id')->chunk(1000, function ($rows) use ($leadCache, $bar) {
                        foreach ($rows as $row) {
                            $leadCache->refreshLeadCache((int) $row->id);
                            $bar->advance();
                        }
                    });
                    $bar->finish();
                    $this->newLine();
                }

                $this->info('✅ Full rebuild completed');

                return Command::SUCCESS;
            }

            // Incremental refresh (last 24 hours)
            $this->info('Incremental refresh (last 24h) for persons...');
            $recentPersons = DB::table('persons')->where('updated_at', '>=', now()->subDay())->pluck('id');
            foreach ($recentPersons as $pid) {
                $personCache->refreshPersonCache((int) $pid);
            }

            $this->info('Incremental refresh (last 24h) for leads...');
            $recentLeads = DB::table('leads')->where('updated_at', '>=', now()->subDay())->pluck('id');
            foreach ($recentLeads as $lid) {
                $leadCache->refreshLeadCache((int) $lid);
            }

            $this->info('✅ Incremental refresh completed');

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('Command failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
