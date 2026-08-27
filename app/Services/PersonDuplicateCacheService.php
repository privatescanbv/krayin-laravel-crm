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
     * Invalidate cache for a person.
     */
    public function invalidatePersonCache(int $personId): void
    {
        $this->invalidateId($personId);
    }

    /**
     * Ids of every (non-trashed) person that currently matches $person on email/phone/name.
     *
     * Used to find whose has_duplicates flag might need recomputing after $person changes or
     * disappears - a duplicate is a pairwise property, so the counterpart must be re-checked too.
     *
     * @return Collection<int, int>
     */
    public function counterpartIdsFor(Person $person): Collection
    {
        return $this->personRepository->findPotentialDuplicatesDirectly($person)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
    }

    /**
     * Recompute + repersist the has_duplicates flag for each id. Ids whose person no longer
     * exists are skipped by refreshPersonCache(). Bounded: callers pass a handful of counterpart
     * ids, never the whole table (that stays the job of duplicates:refresh-cache --index).
     *
     * @param  iterable<int>  $personIds
     */
    public function refreshMany(iterable $personIds): void
    {
        collect($personIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->each(fn (int $id) => $this->refreshPersonCache($id));
    }

    /**
     * Handle person merge: invalidate primary + merged, clear the merged persons' flags,
     * recompute the primary, then recompute every counterpart that only matched a merged-away
     * person so its now-stale has_duplicates flag is cleared immediately (not an hour later).
     *
     * @param  array<int>  $mergedPersonIds
     * @param  array<int>  $counterpartIds  captured before the merged persons were soft-deleted
     */
    public function handlePersonMerge(int $primaryPersonId, array $mergedPersonIds, array $counterpartIds = []): void
    {
        $this->handleMerge($primaryPersonId, $mergedPersonIds);

        foreach ($mergedPersonIds as $id) {
            $this->persistHasDuplicatesFlag((int) $id, collect());
        }

        $this->getCachedDuplicates($primaryPersonId);

        $this->refreshMany($counterpartIds);
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
     * Recompute persons.has_duplicates for the whole table in one pass.
     *
     * Deliberately not per person: detecting duplicates for one person costs ~4 full scans, which
     * runs into hours on a large table. Instead every person is bucketed by the values that make
     * two persons a duplicate (see duplicateKeys), and every bucket holding more than one person
     * flags its members.
     *
     * @return array{processed:int, flagged:int, turned_on:int, turned_off:int}
     */
    public function rebuildHasDuplicatesIndex(): array
    {
        $processed = 0;
        $buckets = [];

        Person::query()
            ->select(['id', 'first_name', 'last_name', 'married_name', 'emails', 'phones'])
            ->orderBy('id')
            ->chunk(2000, function ($persons) use (&$buckets, &$processed) {
                foreach ($persons as $person) {
                    foreach ($this->duplicateKeys($person) as $key) {
                        $buckets[$key][] = (int) $person->id;
                    }

                    $processed++;
                }
            });

        $flagged = [];

        foreach ($buckets as $ids) {
            if (count($ids) < 2) {
                continue;
            }

            foreach ($ids as $id) {
                $flagged[$id] = true;
            }
        }

        unset($buckets);

        $this->applyFalsePositives($flagged);

        return $this->writeFlags($flagged) + ['processed' => $processed, 'flagged' => count($flagged)];
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

    /**
     * The values that make two persons a potential duplicate: any shared key means a match.
     *
     * Mirrors JsonDuplicateMatcher::findDuplicatesByJsonField/findDuplicatesByName, which does the
     * same comparison in SQL for a single person. PersonDuplicateIndexRebuildTest asserts both
     * agree, so a change to the matching rules there fails the test here.
     *
     * @return array<int, string>
     */
    private function duplicateKeys(Person $person): array
    {
        $keys = [];

        foreach (['emails', 'phones'] as $field) {
            foreach ((array) ($person->{$field} ?? []) as $item) {
                $value = is_array($item) ? ($item['value'] ?? '') : $item;

                if ($value !== null && $value !== '') {
                    $keys[] = $field.':'.$value;
                }
            }
        }

        $first = mb_strtolower((string) $person->first_name);

        if ($first !== '') {
            foreach (array_filter([$person->last_name, $person->married_name]) as $name) {
                $keys[] = 'name:'.$first.'|'.mb_strtolower((string) $name);
            }
        }

        return $keys;
    }

    /**
     * Persons carrying a "not a duplicate" marking need the pair-level answer, which the buckets
     * cannot give. There are few of them, so they are recomputed with the regular detection.
     *
     * @param  array<int, bool>  $flagged
     */
    private function applyFalsePositives(array &$flagged): void
    {
        $markedIds = DB::table('duplicates_false_positives')
            ->where('entity_type', DuplicateEntityType::PERSON->value)
            ->get(['entity_id_1', 'entity_id_2'])
            ->flatMap(fn ($row) => [(int) $row->entity_id_1, (int) $row->entity_id_2])
            ->unique();

        foreach ($markedIds as $id) {
            $person = $this->personRepository->find($id);

            if (! $person) {
                continue;
            }

            if ($this->personRepository->findPotentialDuplicates($person)->isNotEmpty()) {
                $flagged[$id] = true;
            } else {
                unset($flagged[$id]);
            }
        }
    }

    /**
     * Write only the flags that actually change, so the update touches few rows.
     *
     * @param  array<int, bool>  $flagged
     * @return array{turned_on:int, turned_off:int}
     */
    private function writeFlags(array $flagged): array
    {
        $currentlyTrue = Person::withTrashed()
            ->where('has_duplicates', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $turnOn = array_diff(array_keys($flagged), $currentlyTrue);
        $turnOff = array_diff($currentlyTrue, array_keys($flagged));

        Person::withoutTimestamps(function () use ($turnOn, $turnOff): void {
            foreach (array_chunk($turnOn, 1000) as $chunk) {
                Person::withTrashed()->whereIn('id', $chunk)->update(['has_duplicates' => true]);
            }

            foreach (array_chunk($turnOff, 1000) as $chunk) {
                Person::withTrashed()->whereIn('id', $chunk)->update(['has_duplicates' => false]);
            }
        });

        return ['turned_on' => count($turnOn), 'turned_off' => count($turnOff)];
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
