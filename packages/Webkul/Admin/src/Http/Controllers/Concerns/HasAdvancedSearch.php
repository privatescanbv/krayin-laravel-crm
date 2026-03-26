<?php

namespace Webkul\Admin\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Prettus\Repository\Contracts\CriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;
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
     * @param array $queryParams Query parameters array (e.g., ['search' => '...', 'searchFields' => '...', 'limit' => 10])
     * @return AnonymousResourceCollection|JsonResponse
     */
    protected function performAdvancedSearch(
        mixed $repository,
        callable $getFieldsSearchable,
        array $eagerLoadRelations,
        callable $getResults,
        string $resourceClass,
        array $queryParams = []
    ): AnonymousResourceCollection|JsonResponse {
        $config = $this->getSearchConfig();

        // Extract query parameters with defaults
        $query = $queryParams['query'] ?? '';
        $search = $queryParams['search'] ?? '';
        $searchFields = $queryParams['searchFields'] ?? '';
        $searchJoin = $queryParams['searchJoin'] ?? 'or';
        $limit = isset($queryParams['limit']) ? (int) $queryParams['limit'] : 10;

        // Validate query length (Bad Request when > 25 characters)
        if (!empty($query) && mb_strlen($query) > 50) {
            return response()->json([
                'message' => __('messages.search.term_too_long'),
            ], 400);
        }

        // Validate and clamp limit
        if ($limit <= 0) {
            $limit = 10;
        }
        if ($limit > 100) {
            $limit = 100;
        }

        // Track if we have a simple query (for email/phone search)
        $simpleQueryTerm = null;

        // Handle simple query parameter (for backward compatibility and entity lookup)
        // Convert query parameter to search format if search/searchFields are not present
        if ($query && !$search && !$searchFields) {
            // Get raw search term for multi-token handling
            $rawSearchTerm = trim((string) $query);

            // Handle multi-token search (AND-of-ORs) - only if not already fielded search
            if (!str_contains($rawSearchTerm, ':')) {
                $tokens = preg_split('/\s+/', $rawSearchTerm, -1, PREG_SPLIT_NO_EMPTY);
                if (count($tokens) > 1) {
                    // Multi-token: apply tokenized AND-of-ORs
                    $this->applyMultiTokenSearch($repository, $tokens, $config['name_fields']);
                    // Clear search params to prevent RequestCriteria from applying additional filters
                    $search = '';
                    $searchFields = '';
                } else {
                    // Single token: convert to name search with like operator
                    $nameFields = $config['name_fields'];
                    $searchTokens = [];
                    $searchFieldTokens = [];

                    foreach ($nameFields as $field) {
                        $searchTokens[] = $field . ':' . $rawSearchTerm;
                        $searchFieldTokens[] = $field . ':like';
                    }

                    $search = implode(';', $searchTokens) . ';';
                    $searchFields = implode(';', $searchFieldTokens) . ';';
                    $searchJoin = 'or';

                    // Store for email/phone search (will be added in normalizeEmailPhoneSearch)
                    $simpleQueryTerm = $rawSearchTerm;
                }
            } else {
                // Already has field tokens, use as-is
                $search = $rawSearchTerm;
            }
        }

        // Handle search parameter without searchFields (common case from frontend)
        // Convert plain text search to proper format if searchFields is missing
        if ($search && !$searchFields && !str_contains($search, ':')) {
            // Plain text search without field tokens - convert to name search
            $rawSearchTerm = trim((string) $search);
            $tokens = preg_split('/\s+/', $rawSearchTerm, -1, PREG_SPLIT_NO_EMPTY);

            if (count($tokens) > 1) {
                // Multi-token: apply tokenized AND-of-ORs
                $this->applyMultiTokenSearch($repository, $tokens, $config['name_fields']);
                // Clear search params to prevent RequestCriteria from applying additional filters
                $search = '';
                $searchFields = '';
            } else {
                // Single token: convert to name search with like operator
                $nameFields = $config['name_fields'];
                $searchTokens = [];
                $searchFieldTokens = [];

                foreach ($nameFields as $field) {
                    $searchTokens[] = $field . ':' . $rawSearchTerm;
                    $searchFieldTokens[] = $field . ':like';
                }

                $search = implode(';', $searchTokens) . ';';
                $searchFields = implode(';', $searchFieldTokens) . ';';
                $searchJoin = 'or';

                // Store for email/phone search (will be added in normalizeEmailPhoneSearch)
                $simpleQueryTerm = $rawSearchTerm;
            }
        }

        // Normalize legacy search params: map `name` to configured name fields
        [$search, $searchFields, $searchJoin] = $this->normalizeNameSearch($config['name_fields'], $search, $searchFields, $searchJoin);

        // Normalize user.name (compact UI token) to first_name/last_name
        if ($config['supports_user_name_search']) {
            [$search, $searchFields, $searchJoin] = $this->normalizeUserNameSearch($search, $searchFields, $searchJoin);
        }

        // Debug logging - enable BEFORE queries are executed
        if ($config['enable_debug_logging'] && $config['table_name']) {
            $this->enableSearchDebugLogging($config['table_name'], $search, $searchFields, $searchJoin);
        }

        // Normalize convenience tokens for email/phone to underlying JSON columns
        $emailTerms = [];
        $phoneTerms = [];
        if ($config['supports_email_phone_search']) {
            [$search, $searchFields, $searchJoin, $emailTerms, $phoneTerms] = $this->normalizeEmailPhoneSearch($search, $searchFields, $searchJoin, $simpleQueryTerm);
        }

        // Log normalized params after email/phone normalization
        if ($config['enable_debug_logging'] && $config['table_name']) {
            Log::info('Search - after email/phone normalization', [
                'search'       => $search,
                'searchFields' => $searchFields,
                'searchJoin'   => $searchJoin,
                'emailTerms'   => $emailTerms,
                'phoneTerms'   => $phoneTerms,
            ]);
        }

        // Validate and sanitize search fields - remove invalid field tokens from search
        $fieldsSearchable = $getFieldsSearchable();
        [$search, $searchFields] = $this->sanitizeInvalidSearchFields($fieldsSearchable, $search, $searchFields);

        // Validate requested search fields against repository's searchable fields
        if ($resp = $this->validateSearchFieldsAgainstAllowed($fieldsSearchable, $searchFields)) {
            return $resp;
        }

        // Apply eager loading
        if (method_exists($repository, 'with')) {
            $repository->with($eagerLoadRelations);
        }

        // Apply limit
        if (method_exists($repository, 'pushCriteria')) {
            // Apply limit via Criteria to compose with other filters
            $limitCriteria = new class($limit) implements CriteriaInterface {
                public function __construct(private int $limit) {}
                public function apply($model, RepositoryInterface $repository)
                {
                    return $model->limit($this->limit);
                }
            };
            $repository->pushCriteria($limitCriteria);
        } elseif ($repository instanceof Builder) {
            $repository->limit($limit);
        }

        // Apply search criteria via RequestCriteria
        // We need to temporarily set request query params for RequestCriteria to work
        // This is a limitation of the RequestCriteria class, but we minimize the dependency
        // Always use the normalized searchFields to ensure phones:like is removed when appropriate
        $originalQuery = request()->query->all();

        // Log what we're passing to RequestCriteria
        if ($config['enable_debug_logging'] && $config['table_name']) {
            Log::info('Search - passing to RequestCriteria', [
                'search'       => $search,
                'searchFields' => $searchFields,
                'searchJoin'   => $searchJoin,
                'emailTerms'   => $emailTerms ?? [],
                'phoneTerms'   => $phoneTerms ?? [],
            ]);
        }

        request()->query->replace(array_merge($originalQuery, [
            'search' => $search,
            'searchFields' => $searchFields, // Use normalized searchFields (phones:like removed if no phone token)
            'searchJoin' => $searchJoin,
        ]));

        try {
            // Get results - RequestCriteria will apply name search
            // Email/phone search should be applied in the getResults callback after RequestCriteria
            // This ensures proper OR combination with name search
            // We pass emailTerms and phoneTerms to getResults via closure
            $results = $getResults($repository, $emailTerms, $phoneTerms);
        } finally {
            // Restore original query params
            request()->query->replace($originalQuery);
        }

        // Return resource collection
        return $resourceClass::collection($results);
    }

    /**
     * Normalize legacy search params: map `name` to first/last/married_name, preserve other tokens.
     *
     * @return array [search, searchFields, searchJoin]
     */
    protected function normalizeNameSearch(array $nameFields, string $search, string $searchFields, string $searchJoin): array
    {
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
                $search = implode(';', $tokens) . ';';

                // Build searchFields: remove name:like; ensure :like for expanded fields, keep others
                $sfParts = array_values(array_filter(array_map('trim', explode(';', (string) $searchFields))));
                $sfParts = array_values(array_filter($sfParts, fn($p) => !str_starts_with($p, 'name:')));
                $existing = array_map(fn($p) => explode(':', $p)[0] ?? $p, $sfParts);
                foreach ($nameFields as $nf) {
                    if (!in_array($nf, $existing, true)) {
                        $sfParts[] = $nf . ':like';
                    }
                }
                $searchFields = implode(';', $sfParts) . ';';

                // Force OR join so tokens are combined permissively
                $searchJoin = 'or';
            }
        }

        return [$search, $searchFields, $searchJoin];
    }

    /**
     * Normalize `user.name:term` into `user.first_name:term;user.last_name:term` with like semantics.
     *
     * @return array [search, searchFields, searchJoin]
     */
    protected function normalizeUserNameSearch(string $search, string $searchFields, string $searchJoin): array
    {
        if (!str_contains($search, 'user.name:')) {
            return [$search, $searchFields, $searchJoin];
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
        $search = implode(';', $rebuilt) . ';';

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
            $searchFields = implode(';', $sfParts) . ';';
        } else {
            $searchFields = 'user.first_name:like;user.last_name:like;';
        }

        // Be permissive between tokens
        $searchJoin = 'or';

        return [$search, $searchFields, $searchJoin];
    }

    /**
     * Normalize convenience tokens for email/phone to underlying JSON columns.
     *
     * @param string|null $simpleQueryTerm Optional simple query term to also search in emails/phones
     * @return array [search, searchFields, searchJoin, emailTerms, phoneTerms]
     */
    protected function normalizeEmailPhoneSearch(string $search, string $searchFields, string $searchJoin, ?string $simpleQueryTerm = null): array
    {
        $emailTerms = [];
        $phoneTerms = [];
        $hasEmailToken = $search && (str_contains($search, 'email:') || str_contains($search, 'emails:'));
        $hasPhoneToken = $search && (str_contains($search, 'phone:') || str_contains($search, 'phones:'));

        // If we have a simple query term (no field tokens), also search in emails/phones
        // This allows simple queries to find results by email/phone even when name search is also applied
        if ($simpleQueryTerm !== null && !$hasEmailToken && !$hasPhoneToken) {
            $emailTerms[] = $simpleQueryTerm;
            $phoneTerms[] = $simpleQueryTerm;
        }

        if ($hasEmailToken || $hasPhoneToken) {
            $tokens = array_values(array_filter(array_map('trim', explode(';', $search))));
            $normalized = [];

            foreach ($tokens as $tok) {
                if (str_starts_with($tok, 'email:')) {
                    $term = trim(substr($tok, strlen('email:')));
                    if ($term !== '') {
                        $emailTerms[] = $term;
                    }
                } elseif (str_starts_with($tok, 'emails:')) {
                    $term = trim(substr($tok, strlen('emails:')));
                    if ($term !== '') {
                        $emailTerms[] = $term;
                    }
                } elseif (str_starts_with($tok, 'phone:')) {
                    $term = trim(substr($tok, strlen('phone:')));
                    if ($term !== '') {
                        $phoneTerms[] = $term;
                    }
                } elseif (str_starts_with($tok, 'phones:')) {
                    $term = trim(substr($tok, strlen('phones:')));
                    if ($term !== '') {
                        $phoneTerms[] = $term;
                    }
                } elseif ($tok !== '') {
                    $normalized[] = $tok;
                }
            }

            // Rebuild search WITHOUT email/phone tokens (they'll be handled via scopeQuery)
            $search = $normalized ? implode(';', $normalized) . ';' : '';

            // Remove emails/phones from searchFields since we handle them via scopeQuery
            // Always remove them if we're processing email/phone tokens, even if only one is present
            if ($searchFields !== '') {
                $parts = array_values(array_filter(array_map('trim', explode(';', $searchFields))));
                $parts = array_values(array_filter($parts, fn($p) =>
                    !str_starts_with($p, 'phones:') && !str_starts_with($p, 'emails:')
                ));
                $searchFields = $parts ? implode(';', $parts) . ';' : '';
            }

            $searchJoin = 'or';
        } elseif ($searchFields !== '') {
            // Even if no email/phone tokens in search, remove emails/phones from searchFields
            // to prevent empty queries (e.g., phones like %%)
            $parts = array_values(array_filter(array_map('trim', explode(';', $searchFields))));
            $parts = array_values(array_filter($parts, fn($p) =>
                !str_starts_with($p, 'phones:') && !str_starts_with($p, 'emails:')
            ));
            $searchFields = $parts ? implode(';', $parts) . ';' : '';
        }

        return [$search, $searchFields, $searchJoin, $emailTerms, $phoneTerms];
    }

    /**
     * Apply JSON-aware matching for emails/phones via scopeQuery or directly on query builder.
     */
    protected function applyEmailPhoneSearch(mixed $repository, array $emailTerms, array $phoneTerms): void
    {
        if (method_exists($repository, 'pushCriteria')) {
            // Repository pattern: use Criteria so it composes with other filters
            // Use orWhere to combine with name search (OR, not AND)
            // This ensures the email/phone search is combined with OR to the name search from RequestCriteria
            $emailPhoneCriteria = new class($emailTerms, $phoneTerms) implements CriteriaInterface {
                public function __construct(private array $emailTerms, private array $phoneTerms) {}
                public function apply($model, RepositoryInterface $repository)
                {
                    // Use orWhere to combine with name search from RequestCriteria
                    return $model->orWhere(function ($qb) {
                        foreach ($this->emailTerms as $term) {
                            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], trim($term));
                            $jsonLike = '%"value":"%' . $escaped . '%"%';
                            $qb->orWhere('emails', 'like', $jsonLike)
                               ->orWhere('emails', 'like', '%' . trim($term) . '%');
                        }
                        foreach ($this->phoneTerms as $term) {
                            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], trim($term));
                            $jsonLike = '%"value":"%' . $escaped . '%"%';
                            $qb->orWhere('phones', 'like', $jsonLike)
                               ->orWhere('phones', 'like', '%' . trim($term) . '%');
                        }
                    });
                }
            };
            $repository->pushCriteria($emailPhoneCriteria);
        } elseif ($repository instanceof Builder) {
            // Direct query builder - use orWhere to combine with name search
            $repository->orWhere(function ($qb) use ($emailTerms, $phoneTerms) {
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
     * Apply permission filter to repository based on authorized user IDs.
     * This ensures that search results are filtered by user permissions.
     *
     * @param mixed $repository Repository instance or query builder
     * @return void
     */
    protected function applyPermissionFilter(mixed $repository): void
    {
        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            if (method_exists($repository, 'pushCriteria')) {
                // Repository pattern: use Criteria so it composes with other filters
                $permissionCriteria = new class($userIds) implements CriteriaInterface {
                    public function __construct(private array $userIds) {}
                    public function apply($model, RepositoryInterface $repository)
                    {
                        return $model->whereIn('user_id', $this->userIds);
                    }
                };
                $repository->pushCriteria($permissionCriteria);
            } elseif ($repository instanceof Builder) {
                // Direct query builder
                $repository->whereIn('user_id', $userIds);
            }
        }
    }

    /**
     * Sanitize search string by removing invalid field tokens and converting them to plain text search.
     *
     * @return array [sanitizedSearch, sanitizedSearchFields]
     */
    protected function sanitizeInvalidSearchFields(array $fieldsSearchable, string $search, string $searchFields): array
    {
        if (empty($search) || !str_contains($search, ':')) {
            return [$search, $searchFields];
        }

        $allowed = [];
        foreach ($fieldsSearchable as $key => $value) {
            $allowed[] = is_int($key) ? $value : $key;
        }

        $tokens = array_filter(array_map('trim', explode(';', $search)));
        $validTokens = [];
        $invalidTerms = [];

        foreach ($tokens as $token) {
            if (!str_contains($token, ':')) {
                // Plain text token, keep as-is
                $validTokens[] = $token;
                continue;
            }

            $parts = explode(':', $token, 2);
            $field = trim($parts[0] ?? '');
            $value = trim($parts[1] ?? '');

            if ($field === '' || $value === '') {
                // Invalid format, treat as plain text
                $invalidTerms[] = $token;
                continue;
            }

            if (in_array($field, $allowed, true)) {
                // Valid field, keep token
                $validTokens[] = $token;
            } else {
                // Invalid field, extract value as plain text search term
                $invalidTerms[] = $value;
            }
        }

        // If we have invalid terms, add them as plain text search on name fields
        if (!empty($invalidTerms)) {
            $nameFields = $this->getSearchConfig()['name_fields'] ?? ['first_name', 'last_name'];
            foreach ($invalidTerms as $term) {
                foreach ($nameFields as $nameField) {
                    $validTokens[] = $nameField . ':' . $term;
                }
            }
            // Update searchFields to include name fields if not already present
            if ($searchFields) {
                $sfParts = array_filter(array_map('trim', explode(';', $searchFields)));
                $existingFields = array_map(fn($p) => explode(':', $p)[0] ?? $p, $sfParts);
                foreach ($nameFields as $nf) {
                    if (!in_array($nf, $existingFields, true)) {
                        $sfParts[] = $nf . ':like';
                    }
                }
                $searchFields = implode(';', $sfParts) . ';';
            } else {
                $searchFields = implode(';', array_map(fn($nf) => $nf . ':like', $nameFields)) . ';';
            }
        }

        $sanitizedSearch = !empty($validTokens) ? implode(';', $validTokens) . ';' : '';

        return [$sanitizedSearch, $searchFields];
    }

    /**
     * Validate requested search fields against allowed fields.
     */
    protected function validateSearchFieldsAgainstAllowed(array $fieldsSearchable, string $searchFields): ?JsonResponse
    {
        if (empty($searchFields)) {
            return null;
        }

        $requestedFields = array_filter(explode(';', $searchFields));
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
                    'message' => __('messages.search.invalid_field'),
                    'field' => $field,
                ], 400);
            }
        }
        return null;
    }

    /**
     * Apply multi-token search (AND-of-ORs) for space-separated terms.
     */
    protected function applyMultiTokenSearch(mixed $repository, array $tokens, array $nameFields): void
    {
        if (method_exists($repository, 'pushCriteria')) {
            $multiTokenCriteria = new class($tokens, $nameFields) implements CriteriaInterface {
                public function __construct(private array $tokens, private array $nameFields) {}
                public function apply($model, RepositoryInterface $repository)
                {
                    return $model->where(function ($qb) {
                        foreach ($this->tokens as $token) {
                            $like = '%' . $token . '%';
                            $qb->where(function ($qq) use ($like) {
                                foreach ($this->nameFields as $field) {
                                    $qq->orWhere($field, 'like', $like);
                                }
                            });
                        }
                    });
                }
            };
            $repository->pushCriteria($multiTokenCriteria);
        } elseif ($repository instanceof Builder) {
            $repository->where(function ($qb) use ($tokens, $nameFields) {
                foreach ($tokens as $token) {
                    $like = '%' . $token . '%';
                    $qb->where(function ($qq) use ($like, $nameFields) {
                        foreach ($nameFields as $field) {
                            $qq->orWhere($field, 'like', $like);
                        }
                    });
                }
            });
        }
    }

    /**
     * Enable debug logging for search queries.
     */
    protected function enableSearchDebugLogging(string $tableName, string $search, string $searchFields, string $searchJoin): void
    {
        try {
            Log::info('Search - normalized params', [
                'search'       => $search,
                'searchFields' => $searchFields,
                'searchJoin'   => $searchJoin,
            ]);

            DB::listen(function ($query) use ($tableName) {
                // Only log queries that touch the specified table
                // Support both MySQL (backticks) and PostgreSQL (double quotes) table name formats
                $sqlLower = strtolower($query->sql);
                $tableNameLower = strtolower($tableName);
                $hasTable = Str::contains($sqlLower, "from `{$tableNameLower}`")
                         || Str::contains($sqlLower, "from \"{$tableNameLower}\"")
                         || Str::contains($sqlLower, "from {$tableNameLower} ");

                if ($hasTable) {
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

