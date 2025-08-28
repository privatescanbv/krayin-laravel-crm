<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

abstract class AbstractDuplicateCacheService
{
    protected string $cacheKeyPrefix;

    protected int $cacheTtlSeconds;

    public function __construct(string $cacheKeyPrefix, int $cacheTtlSeconds = 3600)
    {
        $this->cacheKeyPrefix = rtrim($cacheKeyPrefix, ':').':';
        $this->cacheTtlSeconds = $cacheTtlSeconds;
    }

    public function hasCachedIds(int $id): bool
    {
        return $this->rememberIdsUsing($id, fn () => collect())->isNotEmpty();
    }

    public function invalidateId(int $id): void
    {
        Cache::forget($this->getCacheKey($id));
    }

    public function handleMerge(int $primaryId, array $mergedIds): void
    {
        $this->invalidateId($primaryId);
        foreach ($mergedIds as $id) {
            $this->invalidateId((int) $id);
        }
    }

    public function refreshId(int $id, callable $computeIds): void
    {
        $this->invalidateId($id);
        $this->rememberIdsUsing($id, $computeIds);
    }

    protected function getCacheKey(int $id): string
    {
        return $this->cacheKeyPrefix.$id;
    }

    /**
     * Remember a collection of ids in cache using provided callback.
     */
    protected function rememberIdsUsing(int $id, callable $computeIds): Collection
    {
        $cacheKey = $this->getCacheKey($id);

        try {
            return Cache::remember($cacheKey, $this->cacheTtlSeconds, function () use ($computeIds, $id) {
                try {
                    $ids = call_user_func($computeIds, $id);

                    return $ids instanceof Collection ? $ids : collect($ids);
                } catch (Exception $e) {
                    return collect();
                }
            });
        } catch (Exception $e) {
            return collect();
        }
    }
}
