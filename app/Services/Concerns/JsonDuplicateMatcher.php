<?php

namespace App\Services\Concerns;

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
     */
    protected function findDuplicatesByJsonField(Lead|Person $entity, string $fieldName): Collection
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
                    $results = $query->get();

                    $duplicates = $duplicates->merge($results);
                } catch (Exception $e) {
                    Log::error("Error searching for {$fieldName} duplicates: ".$e->getMessage());
                }
            }
        } catch (Exception $e) {
            Log::error("Error in findDuplicatesByJsonField for {$fieldName}: ".$e->getMessage());
        }

        return $duplicates;
    }

    protected function findDuplicatesByName(Person|Lead $entity): Collection
    {
        if (empty($entity->first_name) && empty($entity->last_name)) {
            return collect();
        }
        $duplicates = collect();

        try {
            $query = $this->model->newQuery()
                ->where('id', '!=', $entity->id);

            // Exact first + last name match (case-insensitive, MySQL compatible)
            if (! empty($entity->first_name) && ! empty($entity->last_name)) {
                $first = mb_strtolower($entity->first_name);
                $last = mb_strtolower($entity->last_name);

                $exactMatches = (clone $query)
                    ->whereRaw('LOWER(first_name) = ?', [$first])
                    ->whereRaw('LOWER(last_name) = ?', [$last])
                    ->get();
                $duplicates = $duplicates->merge($exactMatches);
            }

            // Married name variations
            if (! empty($entity->married_name) && ! empty($entity->first_name)) {
                $firstLower = mb_strtolower($entity->first_name);
                $marriedLower = mb_strtolower($entity->married_name);
                $lastLower = ! empty($entity->last_name) ? mb_strtolower($entity->last_name) : null;

                $marriedQuery = (clone $query)
                    ->whereRaw('LOWER(first_name) = ?', [$firstLower])
                    ->where(function ($q) use ($marriedLower, $lastLower) {
                        $q->whereRaw('LOWER(last_name) = ?', [$marriedLower]);
                        if ($lastLower !== null) {
                            $q->orWhereRaw('LOWER(married_name) = ?', [$lastLower]);
                        }
                    });

                $marriedMatches = $marriedQuery->get();
                $duplicates = $duplicates->merge($marriedMatches);
            }
        } catch (Exception $e) {
            Log::error('Error searching for person name duplicates: '.$e->getMessage());
        }

        return $duplicates;
    }
}
