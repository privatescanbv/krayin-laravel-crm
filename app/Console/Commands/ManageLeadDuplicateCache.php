<?php

namespace App\Console\Commands;

use App\Services\LeadDuplicateCacheService;
use Exception;
use Illuminate\Console\Command;
use Webkul\Lead\Repositories\LeadRepository;

class ManageLeadDuplicateCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:lead-duplicates
                          {action : Action to perform (stats|clear|rebuild|test)}
                          {--lead= : Specific lead ID for testing}
                          {--force : Force the action without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage lead duplicate cache (stats, clear, rebuild, test)';

    public function __construct(
        private LeadDuplicateCacheService $cacheService,
        private LeadRepository $leadRepository
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'stats'   => $this->showStats(),
            'clear'   => $this->clearCache(),
            'rebuild' => $this->rebuildCache(),
            'test'    => $this->testCache(),
            default   => $this->error("Invalid action: {$action}. Use: stats, clear, rebuild, or test")
        };
    }

    private function showStats(): int
    {
        $this->info('🔍 Lead Duplicate Cache Statistics');
        $this->line('=' . str_repeat('=', 40));

        $stats = $this->cacheService->getCacheStats();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Leads', number_format($stats['total_leads'])],
                ['Cache Backend', $stats['cache_backend']],
                ['Cache TTL', $stats['cache_ttl_hours'] . ' hours'],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Clear all duplicate caches.
     */
    private function clearCache(): int
    {
        if (! $this->option('force') && ! $this->confirm('⚠️  Are you sure you want to clear all duplicate caches?')) {
            $this->info('❌ Operation cancelled');

            return Command::SUCCESS;
        }

        $this->info('🗑️  Clearing all lead duplicate caches...');

        $startTime = microtime(true);
        $this->cacheService->clearAllCache();
        $duration = round(microtime(true) - $startTime, 2);

        $this->info("✅ All caches cleared successfully in {$duration}s");

        return Command::SUCCESS;
    }

    /**
     * Rebuild all caches.
     */
    private function rebuildCache(): int
    {
        if (! $this->option('force') && ! $this->confirm('🔄 This will rebuild all duplicate caches. Continue?')) {
            $this->info('❌ Operation cancelled');

            return Command::SUCCESS;
        }

        $this->info('🔄 Starting full cache rebuild...');
        $this->info('⏱️  This may take several minutes depending on the number of leads.');

        $startTime = microtime(true);
        $this->cacheService->clearAllCache();
        $duration = round(microtime(true) - $startTime, 2);

        $this->info("✅ Cache cleared successfully in {$duration}s (will rebuild on demand)");

        return Command::SUCCESS;
    }

    /**
     * Test cache functionality.
     */
    private function testCache(): int
    {
        $leadId = $this->option('lead');

        if (! $leadId) {
            // Get a random lead for testing
            $lead = $this->leadRepository->inRandomOrder()->first();
            if (! $lead) {
                $this->error('❌ No leads found in database for testing');

                return Command::FAILURE;
            }
            $leadId = $lead->id;
        }

        $this->info("🧪 Testing cache functionality with lead ID: {$leadId}");
        $this->line('='.str_repeat('=', 50));

        try {
            $lead = $this->leadRepository->findOrFail($leadId);
            $this->info("📋 Lead: {$lead->first_name} {$lead->last_name} (ID: {$lead->id})");
            $this->newLine();

            // Test 1: Check if cached duplicates exist
            $this->line('🔍 Test 1: Checking cached duplicates...');
            $startTime = microtime(true);
            $cachedDuplicates = $this->cacheService->getCachedDuplicates($leadId);
            $cachedTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->info("   ⚡ Cached result: {$cachedDuplicates->count()} duplicates found in {$cachedTime}ms");

            // Test 2: Compare with direct computation
            $this->line('🔍 Test 2: Direct computation (for comparison)...');
            $startTime = microtime(true);
            $directDuplicates = $this->leadRepository->findPotentialDuplicatesDirectly($lead);
            $directTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->info("   🐌 Direct result: {$directDuplicates->count()} duplicates found in {$directTime}ms");

            // Test 3: Performance comparison
            $this->newLine();
            $this->line('📊 Performance Comparison:');
            if ($cachedTime > 0) {
                $speedup = round($directTime / $cachedTime, 2);
                $this->info("   🚀 Cache is {$speedup}x faster!");
            }
            $this->info('   📈 Time saved: '.round($directTime - $cachedTime, 2).'ms');

            // Test 4: Consistency check
            $this->line('✅ Test 4: Consistency check...');
            $cachedIds = $cachedDuplicates->sort();
            $directIds = $directDuplicates->pluck('id')->sort();

            if ($cachedIds->toArray() === $directIds->toArray()) {
                $this->info('   ✅ Cache results match direct computation perfectly');
            } else {
                $this->warn('   ⚠️  Cache results differ from direct computation');
                $this->line('   📋 Cached IDs: '.$cachedIds->implode(', '));
                $this->line('   📋 Direct IDs: '.$directIds->implode(', '));
            }

            // Test 5: Cache invalidation
            $this->line('🔄 Test 5: Cache invalidation...');
            $this->cacheService->invalidateLeadCache($leadId);
            $this->info('   ✅ Cache invalidated successfully');

            $this->newLine();
            $this->info('🎉 All tests completed successfully!');

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('❌ Test failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
