<?php

namespace App\Services;

use App\Enums\DuplicateEntityType;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Webkul\Lead\Repositories\LeadRepository;

class LeadDuplicateCacheService extends AbstractDuplicateCacheService
{
    private const CACHE_TTL = 3600; // 1 hour (shorter for testing)

    public function __construct(
        private LeadRepository $leadRepository,
        private DuplicateFalsePositiveService $falsePositiveService
    ) {
        parent::__construct('lead_duplicates:', self::CACHE_TTL);
    }

    /**
     * Get cached duplicates for a lead.
     */
    public function getCachedDuplicates(int $leadId): Collection
    {
        $duplicateIds = $this->rememberIdsUsing($leadId, function (int $id) {
            $lead = $this->leadRepository->find($id);
            if (! $lead) {
                return collect();
            }

            return $this->leadRepository->findPotentialDuplicatesDirectly($lead)->pluck('id');
        });

        // Always apply false-positive filtering at read-time (so new markings take effect immediately).
        return $this->falsePositiveService->filterCandidateIdsForPrimary(
            DuplicateEntityType::LEAD,
            $leadId,
            $duplicateIds
        );
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
        $this->invalidateId($leadId);
    }

    /**
     * Handle lead merge - simple invalidation.
     */
    public function handleLeadMerge(int $primaryLeadId, array $mergedLeadIds): void
    {
        // Just invalidate all involved leads
        $this->handleMerge($primaryLeadId, $mergedLeadIds);
    }

    /**
     * Refresh cache for a lead.
     */
    public function refreshLeadCache(int $leadId): void
    {
        $this->refreshId($leadId, function (int $id) {
            $lead = $this->leadRepository->find($id);
            if (! $lead) {
                return collect();
            }

            return $this->leadRepository->findPotentialDuplicatesDirectly($lead)->pluck('id');
        });
    }

    /**
     * Get basic cache stats.
     */
    public function getCacheStats(): array
    {
        $totalLeads = $this->leadRepository->count();

        return [
            'total_leads'     => $totalLeads,
            'cache_ttl_hours' => $this->cacheTtlSeconds / 3600,
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
    // getCacheKey is inherited
}
