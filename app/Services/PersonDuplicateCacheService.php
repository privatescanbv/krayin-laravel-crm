<?php

namespace App\Services;

use App\Enums\DuplicateEntityType;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;

class PersonDuplicateCacheService extends AbstractDuplicateCacheService
{
    private const CACHE_TTL = 3600; // 1 hour (shorter for testing)

    public function __construct(
        private PersonRepository $personRepository,
        private DuplicateFalsePositiveService $falsePositiveService
    ) {
        parent::__construct('person_duplicates:', self::CACHE_TTL);
    }

    /**
     * Get cached duplicates for a person.
     */
    public function getCachedDuplicates(int $personId): Collection
    {
        $duplicateIds = $this->rememberIdsUsing($personId, function (int $id) {
            $person = $this->personRepository->find($id);
            if (! $person) {
                return collect();
            }

            return $this->personRepository->findPotentialDuplicatesDirectly($person)->pluck('id');
        });

        // Always apply false-positive filtering at read-time (so new markings take effect immediately).
        $filteredIds = $this->falsePositiveService->filterCandidateIdsForPrimary(
            DuplicateEntityType::PERSON,
            $personId,
            $duplicateIds
        );

        $this->persistHasDuplicatesFlag($personId, $filteredIds);

        return $filteredIds;
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
        $this->invalidateId($personId);
    }

    /**
     * Handle person merge - simple invalidation.
     */
    public function handlePersonMerge(int $primaryPersonId, array $mergedPersonIds): void
    {
        $this->handleMerge($primaryPersonId, $mergedPersonIds);

        foreach ($mergedPersonIds as $id) {
            $this->persistHasDuplicatesFlag((int) $id, collect());
        }

        $this->getCachedDuplicates($primaryPersonId);
    }

    /**
     * Refresh cache for a person.
     */
    public function refreshPersonCache(int $personId): void
    {
        $this->refreshId($personId, function (int $id) {
            $person = $this->personRepository->find($id);
            if (! $person) {
                return collect();
            }

            return $this->personRepository->findPotentialDuplicatesDirectly($person)->pluck('id');
        });

        $this->getCachedDuplicates($personId);
    }

    /**
     * Count non-deleted persons currently flagged as having duplicates.
     *
     * @param  array<int>|null  $authorizedUserIds  null = no user restriction
     */
    public function countPersonsWithDuplicates(?array $authorizedUserIds = null): int
    {
        $query = Person::query()
            ->where('has_duplicates', true);

        if ($authorizedUserIds !== null) {
            $query->whereIn('user_id', $authorizedUserIds);
        }

        return $query->count();
    }

    /**
     * URL for the persons list pre-filtered to duplicates.
     */
    public function personsIndexUrlWithDuplicateFilter(): string
    {
        return route('admin.contacts.persons.index', [
            'filters' => [
                'has_duplicates' => ['1'],
            ],
        ]);
    }

    /**
     * Recompute has_duplicates for every non-deleted person from cached/live detection.
     */
    public function rebuildHasDuplicatesIndex(): int
    {
        $processed = 0;

        Person::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($persons) use (&$processed) {
                foreach ($persons as $person) {
                    $this->getCachedDuplicates((int) $person->id);
                    $processed++;
                }
            });

        Person::withoutTimestamps(function (): void {
            Person::onlyTrashed()
                ->where('has_duplicates', true)
                ->update(['has_duplicates' => false]);
        });

        return $processed;
    }

    /**
     * Persist the denormalized has_duplicates flag without touching updated_at.
     *
     * @param  Collection<int, int>  $duplicateIds
     */
    public function persistHasDuplicatesFlag(int $personId, Collection $duplicateIds): void
    {
        $hasDuplicates = $duplicateIds->isNotEmpty();

        $this->writeHasDuplicatesFlag($personId, $hasDuplicates);

        if ($hasDuplicates) {
            foreach ($duplicateIds as $duplicateId) {
                $this->writeHasDuplicatesFlag((int) $duplicateId, true);
            }
        }
    }

    /**
     * Get basic cache stats.
     */
    public function getCacheStats(): array
    {
        $totalPersons = $this->personRepository->count();

        // Estimate cached count by sampling keys per person id
        $cachedCount = 0;
        try {
            DB::table('persons')->select('id')->orderBy('id')->chunk(1000, function ($rows) use (&$cachedCount) {
                foreach ($rows as $row) {
                    $cacheKey = $this->getCacheKey((int) $row->id);
                    if (Cache::has($cacheKey)) {
                        $cachedCount++;
                    }
                }
            });
        } catch (Exception $e) {
            Log::warning('Error computing person duplicate cache stats: '.$e->getMessage());
        }

        return [
            'total_persons'   => $totalPersons,
            'cached_count'    => $cachedCount,
            'coverage_pct'    => $totalPersons > 0 ? round(($cachedCount / $totalPersons) * 100, 2) : 0.0,
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
        Log::info('Cleared all person duplicate caches');
    }

    private function writeHasDuplicatesFlag(int $personId, bool $hasDuplicates): void
    {
        Person::withoutTimestamps(function () use ($personId, $hasDuplicates): void {
            Person::withTrashed()
                ->whereKey($personId)
                ->where('has_duplicates', '!=', $hasDuplicates)
                ->update(['has_duplicates' => $hasDuplicates]);
        });
    }

    /**
     * Generate cache key.
     */
    // getCacheKey inherited
}
