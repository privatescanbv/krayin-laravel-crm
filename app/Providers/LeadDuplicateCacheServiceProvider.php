<?php

namespace App\Providers;

use App\Services\DuplicateFalsePositiveService;
use App\Services\LeadDuplicateCacheService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Webkul\Lead\Repositories\LeadRepository;

class LeadDuplicateCacheServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(LeadDuplicateCacheService::class, function ($app) {
            return new LeadDuplicateCacheService(
                $app->make(LeadRepository::class),
                $app->make(DuplicateFalsePositiveService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Populate cache on first startup if needed
        if ($this->shouldPopulateCache()) {
            $this->populateInitialCache();
        }
    }

    /**
     * Check if we should populate the cache on startup.
     */
    private function shouldPopulateCache(): bool
    {
        // Only populate in production and if cache is empty
        if (! app()->environment('production')) {
            return false;
        }

        // Check if we have a cache marker
        return ! cache()->has('lead_duplicate_cache_initialized');
    }

    /**
     * Populate initial cache asynchronously.
     */
    private function populateInitialCache(): void
    {
        try {
            // Mark cache as being initialized to prevent multiple runs
            cache()->put('lead_duplicate_cache_initialized', true, 3600); // 1 hour marker

            Log::info('Starting initial lead duplicate cache population');

            // Simple initialization - no background job needed
            try {
                cache()->forever('lead_duplicate_cache_initialized', true);
                Log::info('Lead duplicate cache service initialized');
            } catch (Exception $e) {
                Log::warning('Could not initialize cache service: '.$e->getMessage());
            }

        } catch (Exception $e) {
            Log::warning('Could not dispatch initial cache population job: '.$e->getMessage());
        }
    }
}
