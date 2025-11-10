<?php

namespace Webkul\Admin\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Prettus\Repository\Criteria\RequestCriteria;
use Throwable;

trait HasAdvancedSearch
{
    /**
     * Configuration for search normalization.
     * Override in controller to customize behavior.
     */
    protected function getSearchConfig(): array
    {
        return [
            // Fields to expand when searching by 'name:'
            'name_fields' => ['first_name', 'last_name', 'married_name'],
            
            // Whether to support email/phone JSON column search
            'supports_email_phone_search' => true,
            
            // Whether to support user.name search
            'supports_user_name_search' => true,
            
            // Whether to enable debug logging
            'enable_debug_logging' => false,
            
            // Table name for SQL logging (e.g., 'leads', 'salesleads')
            'table_name' => null,
        ];
    }

    /**
     * Perform advanced search with normalization.
     * 
     * @param mixed $repository Repository instance or query builder
     * @param callable $getFieldsSearchable Callable that returns array of searchable fields
     * @param array $eagerLoadRelations Relations to eager load
     * @param callable $getResults Callable that returns the search results
     * @param string $resourceClass Resource class to wrap results
     * @return AnonymousResourceCollection|JsonResponse
     */
    protected function performAdvancedSearch(
        mixed $repository,
        callable $getFieldsSearchable,
        array $eagerLoadRelations,
        callable $getResults,
        string $resourceClass
    ): AnonymousResourceCollection|JsonResponse {
        $config = $this->getSearchConfig();

        // Handle simple query parameter (for backward compatibility and entity lookup)
        // Convert query parameter to search format if search/searchFields are not present
        $query = request()->query('query', '');
        $search = request()->query('search', '');
        $searchFields = request()->query('searchFields', '');
        
        if ($query && !$search && !$searchFields) {
            // Simple query mode: convert to name search with like operator
            $nameFields = $config['name_fields'];
            $searchTokens = [];
            $searchFieldTokens = [];
            
            foreach ($nameFields as $field) {
                $searchTokens[] = $field . ':' . $query;
                $searchFieldTokens[] = $field . ':like';
            }
            
            // Merge query parameters (not request body)
            request()->query->add([
                'search' => implode(';', $searchTokens) . ';',
                'searchFields' => implode(';', $searchFieldTokens) . ';',
                'searchJoin' => 'or',
            ]);
        }

        // Normalize legacy search params: map `name` to configured name fields
        $this->normalizeNameSearch($config['name_fields']);

        // Normalize user.name (compact UI token) to first_name/last_name
        if ($config['supports_user_name_search']) {
            $this->normalizeUserNameSearch();
        }

        // Normalize convenience tokens for email/phone to underlying JSON columns
        $emailTerms = [];
        $phoneTerms = [];
        if ($config['supports_email_phone_search']) {
            [$emailTerms, $phoneTerms] = $this->normalizeEmailPhoneSearch();
        }

        // Apply JSON-aware matching for emails/phones via scopeQuery
        if (!empty($emailTerms) || !empty($phoneTerms)) {
            $this->applyEmailPhoneSearch($repository, $emailTerms, $phoneTerms);
        }

        // Validate requested search fields against repository's searchable fields
        $fieldsSearchable = $getFieldsSearchable();
        if ($resp = $this->validateSearchFieldsAgainstAllowed($fieldsSearchable)) {
            return $resp;
        }

        // Debug logging
        if ($config['enable_debug_logging'] && $config['table_name']) {
            $this->enableSearchDebugLogging($config['table_name']);
        }

        // Apply eager loading
        if (method_exists($repository, 'with')) {
            $repository->with($eagerLoadRelations);
        }

        // Get results
        $results = $getResults($repository);

        // Return resource collection
        return $resourceClass::collection($results);
    }

    /**
     * Normalize legacy search params: map `name` to first/last/married_name, preserve other tokens.
     */
    protected function normalizeNameSearch(array $nameFields): void
    {
        $search = request()->query('search', '');
        $searchFields = request()->query('searchFields', '');

        if ($search && str_contains($search, 'name:')) {
            preg_match('/name:([^;]+);?/i', $search, $m);
            $term = isset($m[1]) ? trim($m[1]) : '';
            if ($term !== '') {
                // Remove name:* from search tokens
                $tokens = array_values(array_filter(array_map('trim', explode(';', $search))));
                $tokens = array_values(array_filter($tokens, fn($t) => !str_starts_with($t, 'name:')));
                // Append expanded fields
                foreach ($nameFields as $field) {
                    $tokens[] = $field . ':' . $term;
                }
                $newSearch = implode(';', $tokens) . ';';

                // Build searchFields: remove name:like; ensure :like for expanded fields, keep others
                $sfParts = array_values(array_filter(array_map('trim', explode(';', (string) $searchFields))));
                $sfParts = array_values(array_filter($sfParts, fn($p) => !str_starts_with($p, 'name:')));
                $existing = array_map(fn($p) => explode(':', $p)[0] ?? $p, $sfParts);
                foreach ($nameFields as $nf) {
                    if (!in_array($nf, $existing, true)) {
                        $sfParts[] = $nf . ':like';
                    }
                }
                $newSearchFields = implode(';', $sfParts) . ';';

                // Force OR join so tokens are combined permissively
                request()->query->add([
                    'search' => $newSearch,
                    'searchFields' => $newSearchFields,
                    'searchJoin' => 'or',
                ]);
            }
        }
    }

    /**
     * Normalize `user.name:term` into `user.first_name:term;user.last_name:term` with like semantics.
     */
    protected function normalizeUserNameSearch(): void
    {
        $search = request()->query('search', '');
        $searchFields = request()->query('searchFields', '');

        if (!str_contains($search, 'user.name:')) {
            return;
        }

        // Rewrite search tokens
        $tokens = array_values(array_filter(array_map('trim', explode(';', $search))));
        $rebuilt = [];
        foreach ($tokens as $tok) {
            if (str_starts_with($tok, 'user.name:')) {
                $term = trim(substr($tok, strlen('user.name:')));
                if ($term !== '') {
                    $rebuilt[] = 'user.first_name:' . $term;
                    $rebuilt[] = 'user.last_name:' . $term;
                }
            } else {
                $rebuilt[] = $tok;
            }
        }
        request()->query->add(['search' => implode(';', $rebuilt) . ';']);

        // Update searchFields: replace user.name with first_name/last_name like
        if ($searchFields) {
            $sfParts = array_values(array_filter(array_map('trim', explode(';', $searchFields))));
            $sfParts = array_values(array_filter($sfParts, fn($p) => !str_starts_with($p, 'user.name:') && $p !== 'user.name'));
            $existing = array_map(fn($p) => explode(':', $p)[0] ?? $p, $sfParts);
            foreach (['user.first_name', 'user.last_name'] as $nf) {
                if (!in_array($nf, $existing, true)) {
                    $sfParts[] = $nf . ':like';
                }
            }
            request()->query->add(['searchFields' => implode(';', $sfParts) . ';']);
        } else {
            request()->query->add(['searchFields' => 'user.first_name:like;user.last_name:like;']);
        }

        // Be permissive between tokens
        request()->query->add(['searchJoin' => 'or']);
    }

    /**
     * Normalize convenience tokens for email/phone to underlying JSON columns.
     * Returns [emailTerms, phoneTerms].
     */
    protected function normalizeEmailPhoneSearch(): array
    {
        $search = request()->query('search', '');
        $emailTerms = [];
        $phoneTerms = [];

        if ($search && (str_contains($search, 'email:') || str_contains($search, 'phone:'))) {
            $tokens = array_values(array_filter(array_map('trim', explode(';', $search))));
            $normalized = [];

            foreach ($tokens as $tok) {
                if (str_starts_with($tok, 'email:')) {
                    $term = trim(substr($tok, strlen('email:')));
                    if ($term !== '') {
                        $emailTerms[] = $term;
                    }
                } elseif (str_starts_with($tok, 'phone:')) {
                    $term = trim(substr($tok, strlen('phone:')));
                    if ($term !== '') {
                        $phoneTerms[] = $term;
                    }
                } elseif ($tok !== '') {
                    $normalized[] = $tok;
                }
            }

            // Rebuild search WITHOUT email/phone tokens (they'll be handled via scopeQuery)
            request()->query->add(['search' => $normalized ? implode(';', $normalized) . ';' : '']);

            // Remove emails/phones from searchFields since we handle them via scopeQuery
            $sf = (string) request()->query('searchFields', '');
            if ($sf !== '') {
                $parts = array_values(array_filter(array_map('trim', explode(';', $sf))));
                $parts = array_values(array_filter($parts, fn($p) =>
                    !str_starts_with($p, 'phones:') && !str_starts_with($p, 'emails:')
                ));
                request()->query->add(['searchFields' => $parts ? implode(';', $parts) . ';' : '']);
            }

            request()->query->add(['searchJoin' => 'or']);
        }

        return [$emailTerms, $phoneTerms];
    }

    /**
     * Apply JSON-aware matching for emails/phones via scopeQuery or directly on query builder.
     */
    protected function applyEmailPhoneSearch(mixed $repository, array $emailTerms, array $phoneTerms): void
    {
        if (method_exists($repository, 'scopeQuery')) {
            // Repository pattern
            $repository->scopeQuery(function ($q) use ($emailTerms, $phoneTerms) {
                return $q->where(function ($qb) use ($emailTerms, $phoneTerms) {
                    foreach ($emailTerms as $term) {
                        $escaped = str_replace(['%', '_'], ['\\%', '\\_'], trim($term));
                        $jsonLike = '%"value":"%' . $escaped . '%"%';
                        $qb->orWhere('emails', 'like', $jsonLike)
                           ->orWhere('emails', 'like', '%' . trim($term) . '%');
                    }
                    foreach ($phoneTerms as $term) {
                        $escaped = str_replace(['%', '_'], ['\\%', '\\_'], trim($term));
                        $jsonLike = '%"value":"%' . $escaped . '%"%';
                        $qb->orWhere('phones', 'like', $jsonLike)
                           ->orWhere('phones', 'like', '%' . trim($term) . '%');
                    }
                });
            });
        } elseif ($repository instanceof Builder) {
            // Direct query builder
            $repository->where(function ($qb) use ($emailTerms, $phoneTerms) {
                foreach ($emailTerms as $term) {
                    $escaped = str_replace(['%', '_'], ['\\%', '\\_'], trim($term));
                    $jsonLike = '%"value":"%' . $escaped . '%"%';
                    $qb->orWhere('emails', 'like', $jsonLike)
                       ->orWhere('emails', 'like', '%' . trim($term) . '%');
                }
                foreach ($phoneTerms as $term) {
                    $escaped = str_replace(['%', '_'], ['\\%', '\\_'], trim($term));
                    $jsonLike = '%"value":"%' . $escaped . '%"%';
                    $qb->orWhere('phones', 'like', $jsonLike)
                       ->orWhere('phones', 'like', '%' . trim($term) . '%');
                }
            });
        }
    }

    /**
     * Validate requested search fields against allowed fields.
     */
    protected function validateSearchFieldsAgainstAllowed(array $fieldsSearchable): ?JsonResponse
    {
        $requestedFieldsParam = request()->query('searchFields', '');
        if (empty($requestedFieldsParam)) {
            return null;
        }

        $requestedFields = array_filter(explode(';', $requestedFieldsParam));
        $requestedFieldNames = array_map(function ($f) {
            $parts = explode(':', $f);
            return $parts[0] ?? $f;
        }, $requestedFields);

        $allowed = [];
        foreach ($fieldsSearchable as $key => $value) {
            $allowed[] = is_int($key) ? $value : $key;
        }

        foreach ($requestedFieldNames as $field) {
            if ($field === '') {
                continue;
            }
            if (!in_array($field, $allowed, true)) {
                return response()->json([
                    'message' => 'Invalid search field',
                    'field' => $field,
                ], 400);
            }
        }

        return null;
    }

    /**
     * Enable debug logging for search queries.
     */
    protected function enableSearchDebugLogging(string $tableName): void
    {
        try {
            Log::info('Search - normalized params', [
                'search'       => request()->query('search', ''),
                'searchFields' => request()->query('searchFields', ''),
                'searchJoin'   => request()->query('searchJoin', ''),
            ]);

            DB::listen(function ($query) use ($tableName) {
                // Only log queries that touch the specified table
                if (Str::contains($query->sql, "from `{$tableName}`")) {
                    // Build a best-effort interpolated SQL for readability
                    $interpolated = @vsprintf(
                        str_replace('?', "'%s'", $query->sql),
                        array_map(fn($b) => is_string($b) ? $b : (is_null($b) ? 'NULL' : (string) $b), $query->bindings)
                    );

                    Log::debug('Search SQL', [
                        'sql'           => $query->sql,
                        'bindings'      => $query->bindings,
                        'interpolated'  => $interpolated,
                        'time_ms'       => $query->time,
                    ]);
                }
            });
        } catch (Throwable $e) {
            Log::warning('Failed to attach SQL listener for search', ['error' => $e->getMessage()]);
        }
    }
}

