<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Webkul\Contact\Repositories\PersonRepository;

class PersonDuplicateCacheService
{
    private const CACHE_KEY_PREFIX = 'person_duplicates:';

    private const CACHE_TTL = 3600; // 1 hour (shorter for testing)

    public function __construct(
        private PersonRepository $personRepository
    ) {}

    /**
     * Get cached duplicates for a person.
     */
    public function getCachedDuplicates(int $personId): Collection
    {
        $cacheKey = $this->getCacheKey($personId);

        try {
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($personId) {
                try {
                    $person = $this->personRepository->find($personId);
                    if (! $person) {
                        return collect();
                    }

                    // Use direct method to avoid circular dependency
                    $duplicates = $this->personRepository->findPotentialDuplicatesDirectly($person);

                    // Return only IDs to save memory
                    return $duplicates->pluck('id');
                } catch (Exception $e) {
                    Log::warning("Error caching duplicates for person {$personId}: ".$e->getMessage());

                    return collect();
                }
            });
        } catch (Exception $e) {
            Log::warning("Cache error for person {$personId}: ".$e->getMessage());

            return collect();
        }
    }

    /**
     * Get cached duplicates with full data.
     */
    public function getCachedDuplicatesWithData(int $personId): Collection
    {
        $duplicateIds = $this->getCachedDuplicates($personId);

        if ($duplicateIds->isEmpty()) {
            return collect();
        }

        try {
            return $this->personRepository
                ->with(['organization', 'user'])
                ->whereIn('id', $duplicateIds->toArray())
                ->get();
        } catch (Exception $e) {
            Log::warning("Error loading duplicate data for person {$personId}: ".$e->getMessage());

            return collect();
        }
    }

    /**
     * Check if person has duplicates.
     */
    public function hasCachedDuplicates(int $personId): bool
    {
        return $this->getCachedDuplicates($personId)->isNotEmpty();
    }

    /**
     * Invalidate cache for a person.
     */
    public function invalidatePersonCache(int $personId): void
    {
        $cacheKey = $this->getCacheKey($personId);
        Cache::forget($cacheKey);
    }

    /**
     * Handle person merge - simple invalidation.
     */
    public function handlePersonMerge(int $primaryPersonId, array $mergedPersonIds): void
    {
        // Just invalidate all involved persons
        $this->invalidatePersonCache($primaryPersonId);

        foreach ($mergedPersonIds as $personId) {
            $this->invalidatePersonCache($personId);
        }
    }

    /**
     * Refresh cache for a person.
     */
    public function refreshPersonCache(int $personId): void
    {
        $this->invalidatePersonCache($personId);
        $this->getCachedDuplicates($personId); // Rebuild
    }

    /**
     * Get basic cache stats.
     */
    public function getCacheStats(): array
    {
        $totalPersons = $this->personRepository->count();

        return [
            'total_persons'   => $totalPersons,
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
        Log::info('Cleared all person duplicate caches');
    }

    /**
     * Generate cache key.
     */
    private function getCacheKey(int $personId): string
    {
        return self::CACHE_KEY_PREFIX.$personId;
    }
}