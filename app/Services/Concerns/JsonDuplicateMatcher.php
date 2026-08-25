<?php

namespace App\Services\Concerns;

use Closure;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

/**
 * Shared helper for robust JSON value matching across drivers.
 */
trait JsonDuplicateMatcher
{
    /**
     * Apply robust JSON value match condition to the given query for a field/value.
     * - On sqlite: uses LIKE patterns
     * - On mysql/others: tries whereJsonContains and adds LIKE fallbacks
     */
    protected function applyJsonValueMatch(Builder $query, string $fieldName, string $value): Builder
    {
        if (DB::getDriverName() === 'sqlite') {
            return $query->where($fieldName, 'LIKE', '%"'.$value.'"%');
        }

        return $query->where(function ($q) use ($fieldName, $value) {
            $q->whereJsonContains($fieldName, [['value' => $value]]);
        });
    }

    /**
     * Generic helper to find duplicates based on JSON field values.
     *
     * @param  Closure(Builder): void|null  $scopeQuery  Optional extra constraint (e.g. a recency
     *                                                   window) applied to every candidate query,
     *                                                   so it's pushed down to SQL instead of
     *                                                   filtering the fetched result in PHP.
     */
    protected function findDuplicatesByJsonField(Lead|Person $entity, string $fieldName, ?Closure $scopeQuery = null): Collection
    {
        $duplicates = collect();

        try {
            $personFieldValue = $entity->{$fieldName};
            if (empty($personFieldValue)) {
                return $duplicates;
            }

            // Handle both array and JSON string formats
            $personValues = is_array($personFieldValue) ? $personFieldValue : json_decode($personFieldValue, true);
            if (! is_array($personValues)) {
                return $duplicates;
            }

            foreach ($personValues as $item) {
                $value = is_array($item) ? ($item['value'] ?? '') : $item;
                if (empty($value)) {
                    continue;
                }

                try {
                    $query = $this->model->newQuery()->where('id', '!=', $entity->id);
                    // Use shared trait for robust matching
                    $query = $this->applyJsonValueMatch($query, $fieldName, (string) $value);
                    if ($scopeQuery) {
                        $scopeQuery($query);
                    }
                    $results = $query->get();

                    $duplicates = $duplicates->merge($results);
                } catch (Exception $e) {
                    Log::error("Error searching for $fieldName duplicates: ".$e->getMessage());
                }
            }
        } catch (Exception $e) {
            Log::error("Error in findDuplicatesByJsonField for $fieldName: ".$e->getMessage());
        }

        return $duplicates;
    }

    /**
     * @param  Closure(Builder): void|null  $scopeQuery  See findDuplicatesByJsonField().
     */
    protected function findDuplicatesByName(Person|Lead $entity, ?Closure $scopeQuery = null): Collection
    {
        if (empty($entity->first_name) && empty($entity->last_name)) {
            return collect();
        }
        $duplicates = collect();

        try {
            $query = $this->model->newQuery()
                ->where('id', '!=', $entity->id);
            if ($scopeQuery) {
                $scopeQuery($query);
            }

            // Compare every name the entity carries (last_name and married_name) against both
            // name columns of the candidate, so a match is found from either side of the pair.
            if (! empty($entity->first_name)) {
                $first = mb_strtolower($entity->first_name);

                foreach (array_filter([$entity->last_name, $entity->married_name]) as $name) {
                    $nameLower = mb_strtolower($name);

                    $matches = (clone $query)
                        ->whereRaw('LOWER(first_name) = ?', [$first])
                        ->where(function ($q) use ($nameLower) {
                            $q->whereRaw('LOWER(last_name) = ?', [$nameLower])
                                ->orWhereRaw('LOWER(married_name) = ?', [$nameLower]);
                        })
                        ->get();

                    $duplicates = $duplicates->merge($matches);
                }
            }
        } catch (Exception $e) {
            Log::error('Error searching for person name duplicates: '.$e->getMessage());
        }

        return $duplicates;
    }
}
