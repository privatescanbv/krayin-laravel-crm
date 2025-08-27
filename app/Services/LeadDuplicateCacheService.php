<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Webkul\Lead\Repositories\LeadRepository;

class LeadDuplicateCacheService
{
    private const CACHE_KEY_PREFIX = 'lead_duplicates:';

    private const CACHE_TTL = 3600; // 1 hour (shorter for testing)

    public function __construct(
        private LeadRepository $leadRepository
    ) {}

    /**
     * Get cached duplicates for a lead.
     */
    public function getCachedDuplicates(int $leadId): Collection
    {
        $cacheKey = $this->getCacheKey($leadId);

        try {
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($leadId) {
                try {
                    $lead = $this->leadRepository->find($leadId);
                    if (! $lead) {
                        return collect();
                    }

                    // Use direct method to avoid circular dependency
                    $duplicates = $this->leadRepository->findPotentialDuplicatesDirectly($lead);

                    // Return only IDs to save memory
                    return $duplicates->pluck('id');
                } catch (Exception $e) {
                    Log::warning("Error caching duplicates for lead {$leadId}: ".$e->getMessage());

                    return collect();
                }
            });
        } catch (Exception $e) {
            Log::warning("Cache error for lead {$leadId}: ".$e->getMessage());

            return collect();
        }
    }

    /**
     * Get cached duplicates with full data.
     */
    public function getCachedDuplicatesWithData(int $leadId): Collection
    {
        $duplicateIds = $this->getCachedDuplicates($leadId);

        if ($duplicateIds->isEmpty()) {
            return collect();
        }

        try {
            return $this->leadRepository
                ->with(['stage', 'pipeline', 'user'])
                ->whereIn('id', $duplicateIds->toArray())
                ->get();
        } catch (Exception $e) {
            Log::warning("Error loading duplicate data for lead {$leadId}: ".$e->getMessage());

            return collect();
        }
    }

    /**
     * Check if lead has duplicates.
     */
    public function hasCachedDuplicates(int $leadId): bool
    {
        return $this->getCachedDuplicates($leadId)->isNotEmpty();
    }

    /**
     * Invalidate cache for a lead.
     */
    public function invalidateLeadCache(int $leadId): void
    {
        $cacheKey = $this->getCacheKey($leadId);
        Cache::forget($cacheKey);
    }

    /**
     * Handle lead merge - simple invalidation.
     */
    public function handleLeadMerge(int $primaryLeadId, array $mergedLeadIds): void
    {
        // Just invalidate all involved leads
        $this->invalidateLeadCache($primaryLeadId);

        foreach ($mergedLeadIds as $leadId) {
            $this->invalidateLeadCache($leadId);
        }
    }

    /**
     * Refresh cache for a lead.
     */
    public function refreshLeadCache(int $leadId): void
    {
        $this->invalidateLeadCache($leadId);
        $this->getCachedDuplicates($leadId); // Rebuild
    }

    /**
     * Get basic cache stats.
     */
    public function getCacheStats(): array
    {
        $totalLeads = $this->leadRepository->count();

        return [
            'total_leads'     => $totalLeads,
            'cache_ttl_hours' => self::CACHE_TTL / 3600,
            'cache_backend'   => config('cache.default'),
        ];
    }

    /**
     * Clear all caches (simple version).
     */
    public function clearAllCache(): void
    {
        Cache::flush();
        Log::info('Cleared all lead duplicate caches');
    }

    /**
     * Generate cache key.
     */
    private function getCacheKey(int $leadId): string
    {
        return self::CACHE_KEY_PREFIX.$leadId;
    }
}
