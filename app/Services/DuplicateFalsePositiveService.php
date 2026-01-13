<?php

namespace App\Services;

use App\Enums\DuplicateEntityType;
use App\Models\DuplicateFalsePositive;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DuplicateFalsePositiveService
{
    /**
     * Normalize a pair (lowest id first).
     *
     * @return array{0:int,1:int}
     */
    public function normalizePair(int $entityIdA, int $entityIdB): array
    {
        if ($entityIdA === $entityIdB) {
            throw new InvalidArgumentException('Entity ids must be different');
        }

        return $entityIdA < $entityIdB
            ? [$entityIdA, $entityIdB]
            : [$entityIdB, $entityIdA];
    }

    /**
     * Store false positives for all unique combinations within the given entity ids (n>=2).
     * Reason is currently optional (UI does not supply it yet).
     *
     * @return int Number of pairs attempted (not necessarily newly inserted).
     */
    public function storeForEntities(DuplicateEntityType $entityType, array $entityIds, ?string $reason = null): int
    {
        $ids = collect($entityIds)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();

        if ($ids->count() < 2) {
            throw new InvalidArgumentException('At least 2 entity ids are required');
        }

        // Build all nC2 pairs. Selection sizes are expected to be small in UI.
        $rows = [];
        for ($i = 0; $i < $ids->count(); $i++) {
            for ($j = $i + 1; $j < $ids->count(); $j++) {
                [$a, $b] = $this->normalizePair($ids[$i], $ids[$j]);
                $rows[] = [
                    'entity_type' => $entityType->value,
                    'entity_id_1' => $a,
                    'entity_id_2' => $b,
                    'reason'      => $reason,
                ];
            }
        }

        DB::transaction(function () use ($rows) {
            // Unique constraint prevents duplicates; upsert is safe and concurrency-friendly.
            DuplicateFalsePositive::query()->upsert(
                $rows,
                ['entity_type', 'entity_id_1', 'entity_id_2'],
                ['reason']
            );
        });

        return count($rows);
    }

    /**
     * Check if a combination should be ignored (i.e. exists as false positive).
     */
    public function shouldIgnore(DuplicateEntityType $entityType, int $entityIdA, int $entityIdB): bool
    {
        [$a, $b] = $this->normalizePair($entityIdA, $entityIdB);

        return DuplicateFalsePositive::query()
            ->where('entity_type', $entityType->value)
            ->where('entity_id_1', $a)
            ->where('entity_id_2', $b)
            ->exists();
    }

    /**
     * Filter candidate ids for a given primary id (removes candidates that are stored as false positives with primary).
     *
     * @param  Collection<int,int>|array<int,int>  $candidateIds
     * @return Collection<int,int>
     */
    public function filterCandidateIdsForPrimary(
        DuplicateEntityType $entityType,
        int $primaryId,
        Collection|array $candidateIds
    ): Collection {
        $candidates = $candidateIds instanceof Collection
            ? $candidateIds
            : collect($candidateIds);

        $candidates = $candidates
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0 && $v !== $primaryId)
            ->unique()
            ->values();

        if ($candidates->isEmpty()) {
            return collect();
        }

        $lower = $candidates->filter(fn ($id) => $id < $primaryId)->values();
        $higher = $candidates->filter(fn ($id) => $id > $primaryId)->values();

        $ignored = collect();
        if ($lower->isNotEmpty() || $higher->isNotEmpty()) {
            $ignored = DuplicateFalsePositive::query()
                ->where('entity_type', $entityType->value)
                ->where(function ($q) use ($primaryId, $lower, $higher) {
                    if ($higher->isNotEmpty()) {
                        $q->orWhere(function ($q2) use ($primaryId, $higher) {
                            $q2->where('entity_id_1', $primaryId)
                                ->whereIn('entity_id_2', $higher->all());
                        });
                    }

                    if ($lower->isNotEmpty()) {
                        $q->orWhere(function ($q2) use ($primaryId, $lower) {
                            $q2->where('entity_id_2', $primaryId)
                                ->whereIn('entity_id_1', $lower->all());
                        });
                    }
                })
                ->get(['entity_id_1', 'entity_id_2'])
                ->map(function ($row) use ($primaryId) {
                    // Return the "other" id in the pair.
                    return (int) ($row->entity_id_1 === $primaryId ? $row->entity_id_2 : $row->entity_id_1);
                })
                ->unique()
                ->values();
        }

        if ($ignored->isEmpty()) {
            return $candidates;
        }

        return $candidates->reject(fn ($id) => $ignored->contains($id))->values();
    }
}
