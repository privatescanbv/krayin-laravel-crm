<?php

namespace Webkul\Admin\Http\Controllers\Contact\Persons;

use App\Enums\ContactLabel;
use App\Http\Controllers\Concerns\NormalizesContactFields;
use App\Models\Address;
use BackedEnum;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Prettus\Repository\Criteria\RequestCriteria;
use Throwable;
use Webkul\Admin\DataGrids\Contact\PersonDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\AttributeForm;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Resources\PersonResource;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;

use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;
use App\Services\PersonValidationService;

class PersonController extends Controller
{
    use NormalizesContactFields;
    private bool $enableLogging = false;

    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct(
        protected PersonRepository           $personRepository,
        private readonly LeadRepository      $leadRepository,
        private readonly AttributeRepository $attributeRepository)
    {
        request()->request->add(['entity_type' => 'persons']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->ajax()) {
            return datagrid(PersonDataGrid::class)->process();
        }

        return view('admin::contacts.persons.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin::contacts.persons.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AttributeForm $request): RedirectResponse|JsonResponse
    {
        // Normalize contact fields before validation
        $this->normalizeContactFields($request);

        $request->validate(PersonValidationService::getWebValidationRules($request));
        Event::dispatch('contacts.person.create.before');

        $data = $request->all();
        $data['entity_type'] = 'persons';

        // Normalize enum-like fields to strings for persistence
        if (isset($data['salutation']) && $data['salutation'] instanceof BackedEnum) {
            $data['salutation'] = $data['salutation']->value;
        }
        if (isset($data['gender']) && $data['gender'] instanceof BackedEnum) {
            $data['gender'] = $data['gender']->value;
        }

        // Filter out entity field if present
        if (isset($data['entity'])) {
            unset($data['entity']);
        }

        // Handle empty date fields
        if (isset($data['date_of_birth']) && empty($data['date_of_birth'])) {
            $data['date_of_birth'] = null;
        }

        // Handle empty unique_id field - convert empty string to null to avoid duplicate key errors
        if (isset($data['unique_id']) && empty($data['unique_id'])) {
            $data['unique_id'] = null;
        }

        // Debug logging
        Log::info('Person create request data:', $data);

        $person = $this->personRepository->create($data);

        Event::dispatch('contacts.person.create.after', $person);

        if (request()->ajax()) {
            return response()->json([
                'data' => $person,
                'message' => trans('admin::app.contacts.persons.index.create-success'),
            ]);
        }

        session()->flash('success', trans('admin::app.contacts.persons.index.create-success'));

        return redirect()->route('admin.contacts.persons.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): View
    {
        $person = $this->personRepository->with(['address', 'organization'])->findOrFail($id);

        // Fetch and sort leads: newest first, won/lost to bottom
        try {
            $leadIds = DB::table('lead_persons')->where('person_id', $person->id)->pluck('lead_id');
            $leads = $leadIds->isEmpty()
                ? collect()
                : Lead::with('stage')->whereIn('id', $leadIds)->get();

            $sortedLeads = $leads
                ->sortBy(function($lead) {
                    $isWonLost = $lead->stage && ($lead->stage->is_won || $lead->stage->is_lost) ? 1 : 0;
                    $updatedTs = $lead->updated_at ? $lead->updated_at->getTimestamp() : 0;
                    return [$isWonLost, -$updatedTs];
                })
                ->values();
        } catch (Exception $e) {
            Log::error('Could not load/sort leads for person', ['person_id' => $person->id, 'error' => $e->getMessage()]);
            $sortedLeads = collect();
        }

        // Load anamnesis sorted by newest first
        $person->load(['anamnesis' => function($query) {
            $query->orderBy('updated_at', 'desc');
        }]);

        // Precompute duplicate count (direct detection ensures indicator shows even if cache cold)
        $duplicateCount = $this->personRepository->findPotentialDuplicates($person)->count();


        return view('admin::contacts.persons.view', [
            'person'          => $person,
            'duplicateCount'  => $duplicateCount,
            'sortedLeads'     => $sortedLeads,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View
    {
        $person = $this->personRepository->with('address')->findOrFail($id);

        return view('admin::contacts.persons.edit', compact('person'));
    }

    /**
     * Update the specified resource in storage.
     * @throws Exception when update fails
     */
    public function update(AttributeForm $request, int $id): RedirectResponse|JsonResponse
    {
        // Normalize contact fields before validation
        $this->normalizeContactFields($request);

        try {
            $request->validate(PersonValidationService::getWebValidationRules($request));
        } catch (Exception $e) {
            // for missing error displaying in the UI
            logger()->warning('Person update validation failed', [
                'person_id' => $id,
                'errors' => $e->errors(),
            ]);
            throw $e;
        }

        Event::dispatch('contacts.person.update.before', $id);

        $data = $request->all();
        $data['entity_type'] = 'persons';

        // Normalize enum-like fields to strings for persistence
        if (isset($data['salutation']) && $data['salutation'] instanceof BackedEnum) {
            $data['salutation'] = $data['salutation']->value;
        }
        if (isset($data['gender']) && $data['gender'] instanceof BackedEnum) {
            $data['gender'] = $data['gender']->value;
        }

        // Filter out entity field if present
        if (isset($data['entity'])) {
            unset($data['entity']);
        }

        // Handle empty date fields
        if (isset($data['date_of_birth']) && empty($data['date_of_birth'])) {
            $data['date_of_birth'] = null;
        }

        // Handle empty unique_id field - convert empty string to null to avoid duplicate key errors
        if (isset($data['unique_id']) && empty($data['unique_id'])) {
            $data['unique_id'] = null;
        }

        $person = $this->personRepository->update($data, $id);

        Event::dispatch('contacts.person.update.after', $person);

        if (request()->ajax()) {
            return response()->json([
                'data' => $person,
                'message' => trans('admin::app.contacts.persons.index.update-success'),
            ], 200);
        }

        session()->flash('success', trans('admin::app.contacts.persons.index.update-success'));

        return redirect()->route('admin.contacts.persons.view', $id);
    }

    /**
     * Search person results.
     */
    public function search(): JsonResource|JsonResponse
    {
        // Validate requested search fields
        if ($resp = $this->validateSearchFieldsAgainstAllowed($this->personRepository->getFieldsSearchable())) {
            return $resp;
        }

        // Normalize convenience tokens email:/phone: to underlying JSON columns and apply stricter matching
        $rawSearch = (string) request()->query('search', '');
        if ($rawSearch && (str_contains($rawSearch, 'email:') || str_contains($rawSearch, 'phone:'))) {
            $tokens = array_values(array_filter(array_map('trim', explode(';', $rawSearch))));
            $emailTerms = [];
            $phoneTerms = [];
            $kept = [];
            foreach ($tokens as $tok) {
                if (str_starts_with($tok, 'email:')) {
                    $emailTerms[] = substr($tok, strlen('email:'));
                } elseif (str_starts_with($tok, 'phone:')) {
                    $phoneTerms[] = substr($tok, strlen('phone:'));
                } else {
                    $kept[] = $tok;
                }
            }

            // Rebuild search without convenience tokens (they will be applied via scopeQuery)
            request()->merge(['search' => ($kept ? implode(';', $kept) . ';' : '')]);

            // Ensure searchFields include like for json columns if needed
            $sf = (string) request()->query('searchFields', '');
            $parts = array_values(array_filter(array_map('trim', explode(';', $sf))));
            $fields = array_map(fn($p) => explode(':', $p)[0] ?? $p, $parts);
            foreach (['emails', 'phones'] as $f) {
                if (!in_array($f, $fields, true)) {
                    $parts[] = $f . ':like';
                }
            }
            if (!empty($parts)) {
                request()->merge(['searchFields' => implode(';', $parts) . ';']);
            }

            // Apply stricter JSON matching via scopeQuery to limit false positives
            if (!empty($emailTerms) || !empty($phoneTerms)) {
                $this->personRepository->scopeQuery(function ($q) use ($emailTerms, $phoneTerms) {
                    return $q->where(function ($qb) use ($emailTerms, $phoneTerms) {
                        foreach ($emailTerms as $term) {
                            $like = '%"value":"%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%"%';
                            $qb->orWhere('emails', 'like', $like)
                               ->orWhere('emails', 'like', '%' . $term . '%'); // fallback
                        }
                        foreach ($phoneTerms as $term) {
                            $like = '%"value":"%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%"%';
                            $qb->orWhere('phones', 'like', $like)
                               ->orWhere('phones', 'like', '%' . $term . '%'); // fallback
                        }
                    });
                });
            }
            // Prefer OR join semantics
            request()->merge(['searchJoin' => 'or']);
        }

        // Map incoming lookup term to RequestCriteria expectations (Contactpersoon zoeken)
        $searchTerm = trim((string) (request('search') ?? request('query') ?? request('term') ?? ''));

        // If searchTerm looks like an email, normalize to emails JSON search for better matching
        if ($searchTerm !== '' && filter_var($searchTerm, FILTER_VALIDATE_EMAIL)) {
            // Build a single OR group across name fields and emails JSON for email-like input
            request()->merge([
                'search'       => '',
                'searchFields' => '',
                'searchJoin'   => 'or',
            ]);

            $this->personRepository->scopeQuery(function ($q) use ($searchTerm) {
                $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $searchTerm);
                $jsonLike = '%"value":"%' . $escaped . '%"%';
                $nameLike = '%' . $searchTerm . '%';

                return $q->where(function ($qb) use ($jsonLike, $nameLike, $searchTerm) {
                    $qb->where('first_name', 'like', $nameLike)
                       ->orWhere('last_name', 'like', $nameLike)
                       ->orWhere('married_name', 'like', $nameLike)
                       ->orWhere('emails', 'like', $jsonLike)
                       ->orWhere('emails', 'like', '%' . $searchTerm . '%');
                });
            });

            // Prevent additional name-only criteria from being added below
            $searchTerm = '';
        }

        // Log all SQL hitting the persons table for this request (interpolated)
        if ($this->enableLogging) {
            DB::listen(function ($query) {
                if (Str::contains($query->sql, 'from `persons`')) {
                    $interpolated = @vsprintf(
                        str_replace('?', "'%s'", $query->sql),
                        array_map(fn($b) => is_string($b) ? $b : (is_null($b) ? 'NULL' : (string)$b), $query->bindings)
                    );

                    Log::debug('Person search SQL', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'interpolated' => $interpolated,
                        'time_ms' => $query->time,
                    ]);
                }
            });
        }

        if ($searchTerm !== '') {
            $tokens = preg_split('/\s+/', $searchTerm, -1, PREG_SPLIT_NO_EMPTY);

            if (count($tokens) > 1) {
                // Multi-token: apply tokenized AND-of-ORs; skip RequestCriteria merge to avoid conflicting AND
                $this->personRepository->scopeQuery(function ($q) use ($tokens) {
                    return $q->where(function ($qb) use ($tokens) {
                        foreach ($tokens as $token) {
                            $like = '%' . $token . '%';
                            $qb->where(function ($qq) use ($like) {
                                $qq->where('first_name', 'like', $like)
                                   ->orWhere('last_name', 'like', $like)
                                   ->orWhere('married_name', 'like', $like);
                            });
                        }
                    });
                });
            } else {
                // Single-token input
                $clientProvidedSearchFields = trim((string) request()->query('searchFields', '')) !== '';
                $isFieldedSearch = str_contains($searchTerm, ':');

                if (!$clientProvidedSearchFields && !$isFieldedSearch) {
                    // Free-text input: include emails/phones plus name fields and add JSON-aware matching
                    $searchFields = 'first_name:like;last_name:like;married_name:like';

                    request()->merge([
                        'search' => $searchTerm,
                        'searchFields' => $searchFields,
                        'searchJoin' => 'or',
                    ]);

                    // Add JSON-aware matching for emails/phones separately to avoid conflicts
                    $this->personRepository->scopeQuery(function ($q) use ($searchTerm) {
                        $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $searchTerm);
                        $jsonLike = '%"value":"%' . $escaped . '%"%';

                        return $q->orWhere(function ($qb) use ($jsonLike, $searchTerm) {
                            // Add OR conditions for JSON fields
                            $qb->orWhere('emails', 'like', $jsonLike)
                                ->orWhere('phones', 'like', $jsonLike)
                                ->orWhere('emails', 'like', '%' . $searchTerm . '%')
                                ->orWhere('phones', 'like', '%' . $searchTerm . '%');
                        });
                    });
                } else {
                    logger()->warning('Search term mismatched, no search');
                }
            }
        }
        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $persons = $this->personRepository
                ->pushCriteria(app(RequestCriteria::class))
                ->with(['address'])
                ->findWhereIn('user_id', $userIds);
        } else {
            $persons = $this->personRepository
                ->pushCriteria(app(RequestCriteria::class))
                ->with(['address'])
                ->all();
        }
        // Check if we need to calculate match scores against a lead
        $leadId = request()->get('lead_id');
        if ($leadId) {
            try {
                $lead = app(LeadRepository::class)->with(['address'])->findOrFail($leadId);

                // Calculate match scores for each person
                $personsWithScores = $persons->map(function ($person) use ($lead) {
                    $score = $this->calculateMatchScore($lead, $person);
                    $person->match_score = $score;
                    $person->match_score_percentage = round($score, 1);

                    // Debug: Log which persons are being scored
                    if ($this->enableLogging) {
                        Log::info('Person being scored', [
                            'person_id' => $person->id,
                            'person_name' => $person->name,
                            'person_first_name' => $person->first_name,
                            'person_last_name' => $person->last_name,
                            'calculated_score' => $score,
                        ]);
                    }

                    return $person;
                });

                // Sort by match score (highest first) and only return persons with score > 0
                $personsWithScores = $personsWithScores
                    ->filter(function ($person) {
                        return $person->match_score > 0;
                    })
                    ->sortByDesc('match_score')
                    ->values();

                return PersonResource::collection($personsWithScores);
            } catch (Exception $e) {
                // If lead not found or error in scoring, return regular results
                logger()->warning('Could not calculate match scores for search', [
                    'lead_id' => $leadId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return PersonResource::collection($persons);
    }

    /**
     * Validate requested search fields against repository definitions.
     * Returns JsonResponse(400) on invalid, otherwise null.
     */
    private function validateSearchFieldsAgainstAllowed(array $fieldsSearchable): ?JsonResponse
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
     * Search person results based on lead data with scoring.
     */
    public function searchByLead(Lead $lead): JsonResource
    {
        $persons = Person::query()
            ->with(['address'])
            ->where('first_name', 'like', '%' . $lead->first_name . '%')
            ->where(function ($query) use ($lead) {
                $query->where('last_name', 'like', '%' . $lead->last_name . '%')
                    ->orWhere('married_name', 'like', '%' . $lead->married_name . '%');
            })
            ->limit(30)
            ->get();

        $personsWithScores = collect($persons)
            ->map(function ($person) use ($lead) {
                $score = $this->calculateMatchScore($lead, $person);

                if ($score > 0) {
                    $person->match_score = $score;
                    $person->match_score_percentage = round($score, 1);;
                    return [$person->id => $person];
                }
                return null;
            })
            ->sortByDesc(function ($item) {
                // $item is an array: [id => $person]
                // Get the person object from the array value
                if (is_array($item)) {
                    $person = reset($item);
                    return $person->match_score ?? 0;
                }
                return 0;
            })
            ->flatMap(function ($item) {
                return $item;
            })// Limit to top 10 results
            ->all();

        return PersonResource::collection($personsWithScores);
    }

    /**
     * Return match score breakdown between a lead and a person for UI tooltips.
     */
    public function matchScoreBreakdown(int $personId, int $leadId): JsonResponse
    {
        try {
            $lead = Lead::findOrFail($leadId);
            $person = Person::findOrFail($personId);

            $result = $this->buildLeadToPersonMatchBreakdown($lead, $person);

            // Compute legacy breakdown data for UI (weights and matched flags)
            $legacyBreakdown = $this->buildLegacyBreakdown($lead, $person, $result['percentage']);

            return response()->json([
                'lead_id' => $lead->id,
                'person_id' => $person->id,
                'percentage' => $result['percentage'],
                'total_fields' => $result['total_fields'],
                'matching_fields' => $result['matching_fields'],
                'field_differences' => $result['field_differences'],
                'breakdown' => $legacyBreakdown,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Unable to calculate match score breakdown',
            ], 400);
        }
    }

    /**
     * Simplified single-person scoring API: /admin/contacts/persons/searchByLead?lead_id=..&person_id=..
     * Returns score percentage and breakdown for reuse in UI components.
     */
    public function searchByLeadSingle(): JsonResponse
    {
        $leadId = (int) request()->query('lead_id');
        $personId = (int) request()->query('person_id');
        if (! $leadId || ! $personId) {
            return response()->json(['message' => 'lead_id and person_id are required'], 422);
        }

        try {
            $lead = Lead::findOrFail($leadId);
            $person = Person::findOrFail($personId);

            $result = $this->buildLeadToPersonMatchBreakdown($lead, $person);

            // Compute legacy breakdown data for UI (weights and matched flags)
            $legacyBreakdown = $this->buildLegacyBreakdown($lead, $person, $result['percentage']);

            return response()->json([
                'person' => [
                    'id' => $person->id,
                    'name' => $person->name,
                    'match_score_percentage' => $result['percentage'],
                ],
                'breakdown' => $legacyBreakdown,
            ]);
        } catch (Throwable $e) {
            Log::error('Error in searchByLeadSingle', [
                'lead_id' => $leadId,
                'person_id' => $personId,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Unable to calculate match score',
            ], 400);
        }
    }

    /**
     * Build match result for lead → person (one-way) comparison.
     * Only considers fields that are present on the lead.
     *
     * @return array{
     *   percentage: float,
     *   total_fields: int,
     *   matching_fields: int,
     *   field_differences: array<string, array{
     *     label: string,
     *     lead_value: null|string,
     *     person_value: null|string,
     *     type: string
     *   }>
     * }
     */
    private function buildLeadToPersonMatchBreakdown(Lead $lead, Person $person): array
    {
        $comparableFields = [
            'salutation' => 'Aanhef',
            'first_name' => 'Voornaam',
            'last_name' => 'Achternaam',
            'lastname_prefix' => 'Voorvoegsel achternaam',
            'married_name' => 'Gehuwde naam',
            'married_name_prefix' => 'Voorvoegsel gehuwde naam',
            'initials' => 'Initialen',
            'date_of_birth' => 'Geboortedatum',
            'gender' => 'Geslacht',
            'emails' => 'E-mailadressen',
            'phones' => 'Telefoonnummers',
        ];

        $addressFields = [
            'street' => 'Straat',
            'house_number' => 'Huisnummer',
            'house_number_suffix' => 'Huisnummer toevoeging',
            'postal_code' => 'Postcode',
            'city' => 'Plaats',
            'country' => 'Land',
        ];

        $fieldDifferences = [];
        $totalFields = 0;
        $matchingFields = 0;

        // Check regular fields
        foreach ($comparableFields as $field => $label) {
            $leadValue = $lead->$field ?? null;

            // Only consider fields that have a value in the lead
            if ($this->hasValue($leadValue)) {
                $totalFields++;
                $personValue = $person->$field ?? null;

                $isMatch = $this->valuesMatch($leadValue, $personValue, $field, 'lead');
                if ($isMatch) {
                    $matchingFields++;
                } else {
                    $fieldDifferences[$field] = [
                        'label' => $label,
                        'lead_value' => $this->formatValueForDisplay($leadValue, $field),
                        'person_value' => $this->formatValueForDisplay($personValue, $field),
                        'type' => $this->getFieldType($field)
                    ];
                }
            }
        }

        // Check address fields
        if ($lead->address) {
            foreach ($addressFields as $field => $label) {
                $leadValue = $lead->address->$field ?? null;

                // Only consider fields that have a value in the lead
                if ($this->hasValue($leadValue)) {
                    $totalFields++;
                    $personValue = $person->address->$field ?? null;

                    $isMatch = $this->valuesMatch($leadValue, $personValue, $field);
                    if ($isMatch) {
                        $matchingFields++;
                    } else {
                        $fieldDifferences["address_{$field}"] = [
                            'label' => $label,
                            'lead_value' => $this->formatValueForDisplay($leadValue, $field),
                            'person_value' => $this->formatValueForDisplay($personValue, $field),
                            'type' => 'text'
                        ];
                    }
                }
            }
        }

        // Calculate percentage
        $percentage = $totalFields > 0 ? ($matchingFields / $totalFields) * 100 : 0;

        return [
            'percentage' => round($percentage, 1),
            'total_fields' => $totalFields,
            'matching_fields' => $matchingFields,
            'field_differences' => $fieldDifferences,
        ];
    }

    /**
     * Format value for display in the UI.
     */
    private function formatValueForDisplay($value, $field): string
    {
        if (is_null($value)) {
            return 'Geen waarde';
        }

        if (in_array($field, ['emails', 'phones'])) {
            if (is_array($value)) {
                $values = $this->extractArrayValues($value);
                return implode(', ', $values) ?: 'Geen waarde';
            }
            return (string) $value;
        }

        if ($field === 'date_of_birth') {
            $formatted = $this->formatDateForComparison($value);
            return $formatted ?: 'Geen waarde';
        }

        // For enums, return backing value for display
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
    }

    /**
     * Build match breakdown and final percentage for a lead/person pair.
     *
     * @return array{
     *     percentage: float,
     *     breakdown: array{
     *         name: array{ratio: float, weighted: float, weight: float},
     *         email: array{matched: bool, weighted: float, weight: float},
     *         phone: array{matched: bool, weighted: float, weight: float},
     *         address: array{matched: bool, weighted: float, weight: float},
     *         final: array{score: float}
     *     },
     *     breakdown_detailed: array{
     *         name: array{
     *             ratio: float,
     *             weighted: float,
     *             weight: float,
     *             fields: array<string, array{0: null|string, 1: null|string}>
     *         },
     *         email: array{matched: bool, weighted: float, weight: float, lead: array, person: array},
     *         phone: array{matched: bool, weighted: float, weight: float, lead: array, person: array},
     *         address: array{
     *             matched: bool,
     *             weighted: float,
     *             weight: float,
     *             lead: array<string, null|string>,
     *             person: array<string, null|string>
     *         },
     *         final: array{score: float}
     *     }
     * }
     */

    /**
     * Calculate match score between lead and person.
     * Only considers fields that are filled in the lead and exist in the person.
     *  If lead values match person values exactly, score is 100%.
     */
    public function calculateMatchScore(Lead $lead, Person $person): float
    {
        $comparableFields = [
            'salutation',
            'first_name',
            'last_name',
            'lastname_prefix',
            'married_name',
            'married_name_prefix',
            'initials',
            'date_of_birth',
            'gender',
            'emails',
            'phones',
        ];

        $addressFields = [
            'street',
            'house_number',
            'house_number_suffix',
            'postal_code',
            'city',
            'country',
        ];

        $totalFields = 0;
        $matchingFields = 0;

        // Check regular fields
        foreach ($comparableFields as $field) {
            $leadValue = $lead->$field ?? null;

            // Only consider fields that have a value in the lead
            if ($this->hasValue($leadValue)) {
                $totalFields++;
                $personValue = $person->$field ?? null;

                // Use lead perspective for array subset logic on emails/phones
                if ($this->valuesMatch($leadValue, $personValue, $field, 'lead')) {
                    $matchingFields++;
                }
            }
        }

        // Check address fields
        if ($lead->address) {
            foreach ($addressFields as $field) {
                $leadValue = $lead->address->$field ?? null;

                // Only consider fields that have a value in the lead
                if ($this->hasValue($leadValue)) {
                    $totalFields++;
                    $personValue = $person->address->$field ?? null;

                    if ($this->valuesMatch($leadValue, $personValue, $field)) {
                        $matchingFields++;
                    }
                }
            }
        }

        // If no fields to compare, return 0
        if ($totalFields === 0) {
            return 0.0;
        }

        // Calculate percentage
        $percentage = ($matchingFields / $totalFields) * 100;

        return min($percentage, 100.0);
    }

    /**
     * Check if a value is considered "has value" (not empty/null).
     */
    private function hasValue($value): bool
    {
        if (is_null($value)) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return !empty($value);
        }

        return true;
    }

    /**
     * Check if two values match for comparison.
     */
    private function valuesMatch($leadValue, $personValue, $field, string $perspective = 'generic'): bool
    {
        // Handle array fields (emails, phones)
        if (in_array($field, ['emails', 'phones'])) {
            return $this->arrayValuesMatch($leadValue, $personValue, $perspective);
        }

        // Handle date fields
        if ($field === 'date_of_birth') {
            $leadDate = $this->formatDateForComparison($leadValue);
            $personDate = $this->formatDateForComparison($personValue);
            return $leadDate && $personDate && $leadDate === $personDate;
        }

        // Handle regular string fields
        $leadNormalized = $this->normalizeValue($leadValue);
        $personNormalized = $this->normalizeValue($personValue);

        return $leadNormalized === $personNormalized;
    }

    /**
     * Check if array values match (for emails/phones).
     */
    private function arrayValuesMatch($leadArray, $personArray, string $perspective = 'generic'): bool
    {
        if (!is_array($leadArray) || !is_array($personArray)) {
            return false;
        }

        $leadValues = $this->extractArrayValues($leadArray);
        $personValues = $this->extractArrayValues($personArray);

        // For sync (lead perspective): treat as match if all lead values exist in person values (subset)
        if ($perspective === 'lead') {
            $personSet = array_map('strtolower', $personValues);
            foreach ($leadValues as $lv) {
                if (!in_array(strtolower($lv), $personSet, true)) {
                    return false;
                }
            }
            return true;
        }

        // Generic: exact match
        sort($leadValues);
        sort($personValues);
        return $leadValues === $personValues;
    }

    /**
     * Extract values from array format (emails/phones).
     */
    private function extractArrayValues($array): array
    {
        $values = [];

        foreach ($array as $item) {
            if (is_array($item) && isset($item['value'])) {
                $values[] = trim($item['value']);
            } elseif (is_string($item)) {
                $values[] = trim($item);
            }
        }

        return array_filter($values);
    }

    /**
     * Normalize value for comparison.
     */
    private function normalizeValue($value): string
    {
        if (is_null($value)) {
            return '';
        }

        // Unwrap backed enums to their scalar backing values for comparison
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if (is_string($value)) {
            return strtolower(trim($value));
        }

        return strtolower(trim((string) $value));
    }

    /**
     * Build legacy breakdown (name/email/phone/address/final) for UI widgets.
     * name.weighted derives from name fields match ratio (0..1) * 85.
     * email/phone weighted = 5 when subset matches, else 0. address weighted approx = (matches/fields)*5.
     */
    private function buildLegacyBreakdown(Lead $lead, Person $person, float $finalPercentage): array
    {
        // Name ratio: recompute from individual name fields considered on the lead
        $nameFields = ['first_name','last_name','lastname_prefix','married_name','married_name_prefix','initials','date_of_birth'];
        $nameTotal = 0; $nameMatches = 0;
        foreach ($nameFields as $nf) {
            $leadVal = $lead->$nf ?? null;
            if ($this->hasValue($leadVal)) {
                $nameTotal++;
                $personVal = $person->$nf ?? null;
                if ($this->valuesMatch($leadVal, $personVal, $nf)) {
                    $nameMatches++;
                }
            }
        }
        $nameRatio = $nameTotal > 0 ? ($nameMatches / $nameTotal) : 0.0;
        $nameWeighted = $nameRatio * 0.85 * 100; // keep original 0..100 scale for weighted

        // Email subset match
        $emailLead = $this->extractEmails($lead);
        $emailPerson = $this->extractEmails($person);
        $emailMatched = false;
        if (!empty($emailLead)) {
            $set = array_map('strtolower', $emailPerson);
            $emailMatched = true;
            foreach ($emailLead as $e) {
                if (!in_array(strtolower($e), $set, true)) {
                    $emailMatched = false; break;
                }
            }
        }
        $emailWeighted = $emailMatched ? (0.05 * 100) : 0;

        // Phone subset match (normalized)
        $phoneLead = $this->extractPhones($lead);
        $phonePerson = $this->extractPhones($person);
        $normalizedPersonPhones = array_map(fn($p) => $this->normalizePhoneNumber($p), $phonePerson);
        $phoneMatched = false;
        if (!empty($phoneLead)) {
            $phoneMatched = true;
            foreach ($phoneLead as $p) {
                if (!in_array($this->normalizePhoneNumber($p), $normalizedPersonPhones, true)) {
                    $phoneMatched = false; break;
                }
            }
        }
        $phoneWeighted = $phoneMatched ? (0.05 * 100) : 0;

        // Address approx matching: compare basic fields equality count
        $leadAddr = $lead->address;
        $personAddr = $person->address;
        $addressWeighted = 0;
        $addressMatched = false;
        if ($leadAddr && $personAddr) {
            $fields = ['street','house_number','postal_code','city','country'];
            $total = 0; $matches = 0;
            foreach ($fields as $f) {
                $lv = strtolower(trim((string)($leadAddr->$f ?? '')));
                $pv = strtolower(trim((string)($personAddr->$f ?? '')));
                if ($lv !== '' || $pv !== '') {
                    $total++;
                    if ($lv !== '' && $pv !== '' && $lv === $pv) {
                        $matches++;
                    } elseif ($f === 'postal_code' && $lv !== '' && $pv !== '') {
                        if (str_contains($lv, $pv) || str_contains($pv, $lv)) {
                            $matches += 0.5;
                        }
                    }
                }
            }
            if ($total > 0) {
                $ratio = $matches / $total; // 0..1
                $addressWeighted = $ratio * 0.05 * 100;
                $addressMatched = $ratio > 0; // some match
            }
        }

        return [
            'name' => [ 'ratio' => $nameRatio, 'weighted' => $nameWeighted, 'weight' => 0.85 ],
            'email' => [ 'matched' => $emailMatched, 'weighted' => $emailWeighted, 'weight' => 0.05 ],
            'phone' => [ 'matched' => $phoneMatched, 'weighted' => $phoneWeighted, 'weight' => 0.05 ],
            'address' => [ 'matched' => $addressMatched, 'weighted' => $addressWeighted, 'weight' => 0.05 ],
            'final' => [ 'score' => $finalPercentage ],
        ];
    }

    /**
     * Extract emails from lead or person.
     */
    private function extractEmails($entity): array
    {
        $emails = [];

        // Handle array format (from emails field)
        if (!empty($entity->emails) && is_array($entity->emails)) {
            foreach ($entity->emails as $email) {
                if (is_array($email) && !empty($email['value'])) {
                    $emails[] = $email['value'];
                } elseif (is_string($email)) {
                    $emails[] = $email;
                }
            }
        }

        // Handle single email field (if exists)
        if (!empty($entity->email)) {
            $emails[] = $entity->email;
        }

        return array_filter($emails);
    }

    /**
     * Extract phone numbers from lead or person.
     */
    private function extractPhones($entity): array
    {
        $phones = [];

        // Handle array format (from phones or contact_numbers field)
        if (!empty($entity->phones) && is_array($entity->phones)) {
            foreach ($entity->phones as $phone) {
                if (is_array($phone) && !empty($phone['value'])) {
                    $phones[] = $phone['value'];
                } elseif (is_string($phone)) {
                    $phones[] = $phone;
                }
            }
        }

        // Handle single phone field (if exists)
        if (!empty($entity->phone)) {
            $phones[] = $entity->phone;
        }

        return array_filter($phones);
    }

    /**
     * Normalize phone number for comparison.
     */
    private function normalizePhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $normalized = preg_replace('/[^0-9]/', '', $phone);

        // Handle Dutch phone numbers - convert +31 to 0
        if (str_starts_with($normalized, '31') && strlen($normalized) >= 10) {
            $normalized = '0' . substr($normalized, 2);
        }

        return $normalized;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $person = $this->personRepository->findOrFail($id);

        try {
            Event::dispatch('contacts.person.delete.before', $id);

            $person->delete($id);

            Event::dispatch('contacts.person.delete.after', $id);

            return response()->json([
                'message' => trans('admin::app.contacts.persons.index.delete-success'),
            ], 200);
        } catch (Exception $exception) {
            Log::error('Could not delete person: ' . $exception->getMessage(), ['person_id' => $id]);
            return response()->json([
                'message' => trans('admin::app.contacts.persons.index.delete-failed'),
            ], 400);
        }
    }

    /**
     * Mass Delete the specified resources.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $persons = $this->personRepository->findWhereIn('id', $massDestroyRequest->get('indices'));

        foreach ($persons as $person) {
            Event::dispatch('contact.person.delete.before', $person);

            $this->personRepository->delete($person->id);

            Event::dispatch('contact.person.delete.after', $person);
        }

        return response()->json([
            'message' => trans('admin::app.contacts.persons.index.delete-success'),
        ]);
    }

    /**
     * Show the form for syncing lead data to person (1-way sync).
     */
    public function syncLeadToPerson(int $leadId, int $personId): View
    {
        $lead = app(LeadRepository::class)->findOrFail($leadId);
        $person = $this->personRepository->findOrFail($personId);

        $matchBreakdown = $this->buildLeadToPersonMatchBreakdown($lead, $person);

        return view('admin::leads.sync-lead-to-person', compact('lead', 'person', 'matchBreakdown'));
    }

    // Removed legacy edit-with-lead and update-with-lead in favor of sync-lead-to-person

    /**
     * Sync lead data to person (1-way sync).
     */
    public function syncLeadToPersonUpdate(int $leadId, int $personId)
    {
        $lead = $this->leadRepository->findOrFail($leadId);
        $person = $this->personRepository->findOrFail($personId);

        $data = request()->all();
        $choices = $data['choice'] ?? [];

        try {
            $personUpdate = [];
            $personAddressUpdate = [];
            $leadUpdate = [];
            $leadAddressUpdate = [];

            foreach ($choices as $field => $which) {
                // Address subfields
                if (str_starts_with($field, 'address_')) {
                    $addressField = substr($field, 8);
                    if ($which === 'lead') {
                        $leadValue = $lead->address->$addressField ?? null;
                        if ($this->hasValue($leadValue)) {
                            $personAddressUpdate[$addressField] = $leadValue;
                        }
                    } else { // person chosen -> update lead
                        $personValue = $person->address->$addressField ?? null;
                        if ($this->hasValue($personValue)) {
                            $leadAddressUpdate[$addressField] = $personValue;
                        }
                    }
                    continue;
                }

                // Regular fields
                if ($which === 'lead') {
                    if (in_array($field, ['emails', 'phones'], true)) {
                        $personCurrent = is_array($person->$field) ? $person->$field : [];
                        $leadValues = is_array($lead->$field) ? $lead->$field : [];
                        $merged = $this->appendContactsSetDefault($personCurrent, $leadValues);
                        if (!empty($merged)) {
                            $personUpdate[$field] = $merged;
                        }
                    } else {
                        $value = $lead->$field ?? null;
                        if ($this->hasValue($value)) {
                            $personUpdate[$field] = $value;
                        }
                    }
                } else { // person
                    if (in_array($field, ['emails', 'phones'], true)) {
                        $leadUpdate[$field] = is_array($person->$field) ? $person->$field : [];
                    } else {
                        $leadUpdate[$field] = $person->$field ?? null;
                    }
                }
            }

            if (!empty($personUpdate)) {
                $person->update($personUpdate);
            }
            if (!empty($personAddressUpdate)) {
                $this->updatePersonAddress($person, $personAddressUpdate);
            }
            if (!empty($leadUpdate)) {
                $lead->update($leadUpdate);
            }
            if (!empty($leadAddressUpdate)) {
                $this->updateLeadAddress($lead, $leadAddressUpdate);
            }

            // Check if it's an AJAX request
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json([
                    'message' => 'Lead gegevens succesvol overgenomen naar person.',
                    'redirect_url' => route('admin.leads.view', $lead->id)
                ]);
            }
            return redirect()->route('admin.leads.view', $lead->id);

        } catch (Exception $e) {
            logger()->error('Could not sync lead to person ' . $e->getMessage(), [
                'person_id' => $personId,
                'lead_id' => $leadId,
                'data' => $data
            ]);

            if (request()->expectsJson() || request()->ajax()) {
                return response()->json([
                    'message' => 'Er is een fout opgetreden: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()->withErrors(['message' => 'Er is een fout opgetreden: ' . $e->getMessage()]);
        }
    }

    /**
     * Update person address with provided data.
     */
    private function updatePersonAddress($person, $addressData): void
    {
        if (empty($addressData)) {
            return;
        }

        // Add person_id to address data
        $addressData['person_id'] = $person->id;

        // Delete existing address if it exists
        if ($person->address) {
            $person->address->delete();
        }

        // Create new address for person
        Address::create($addressData);
    }

    /**
     * Update lead address with provided data.
     */
    private function updateLeadAddress($lead, $addressData): void
    {
        if (empty($addressData)) {
            return;
        }

        // Add lead_id to address data
        $addressData['lead_id'] = $lead->id;

        // Delete existing address if it exists
        if ($lead->address) {
            $lead->address->delete();
        }

        // Create new address for lead
        Address::create($addressData);
    }

    /**
     * Append lead emails/phones to person and set the first added as default.
     * Returns normalized array of contact entries [{value,label,is_default}, ...]
     */
    private function appendContactsSetDefault(array $personContacts, array $leadContacts): array
    {
        $normalizedPerson = [];
        foreach ($personContacts as $idx => $item) {
            if (is_array($item) && isset($item['value'])) {
                $normalizedPerson[] = [
                    'value' => trim((string) $item['value']),
                    'label' => $item['label'] ?? ContactLabel::default()->value,
                    'is_default' => (bool) ($item['is_default'] ?? false),
                ];
            } elseif (is_string($item)) {
                $normalizedPerson[] = [
                    'value' => trim($item),
                    'label' => ContactLabel::default()->value,
                    'is_default' => $idx === 0,
                ];
            }
        }

        $existingValues = array_map(fn($i) => strtolower($i['value']), $normalizedPerson);

        $addedAny = false;
        foreach ($leadContacts as $item) {
            $value = null;
            if (is_array($item) && isset($item['value'])) {
                $value = trim((string) $item['value']);
            } elseif (is_string($item)) {
                $value = trim($item);
            }
            if ($value === null || $value === '') {
                continue;
            }
            if (!in_array(strtolower($value), $existingValues, true)) {
                $normalizedPerson[] = [
                    'value' => $value,
                    'label' => ContactLabel::default()->value,
                    'is_default' => false, // set later
                ];
                $existingValues[] = strtolower($value);
                $addedAny = true;
            }
        }

        // Set default: if we added any, make the last added the default
        if ($addedAny) {
            foreach ($normalizedPerson as &$row) {
                $row['is_default'] = false;
            }
            $normalizedPerson[count($normalizedPerson) - 1]['is_default'] = true;
            unset($row);
        }

        return $normalizedPerson;
    }

    /**
     * Format date for comparison, handling invalid dates.
     */
    private function formatDateForComparison($date): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            // Check if it's a valid Carbon instance
            if ($date instanceof Carbon) {
                // Check for invalid dates (like -0001-11-30 or 0000-00-00)
                if ($date->year <= 0 || $date->year > 2100) {
                    return null;
                }
                return $date->format('Y-m-d');
            }

            // If it's a string, try to parse it
            if (is_string($date)) {
                // Skip obviously invalid dates
                if (in_array($date, ['0000-00-00', '0000-00-00 00:00:00']) || str_starts_with($date, '-0001')) {
                    return null;
                }

                $carbonDate = Carbon::parse($date);
                if ($carbonDate->year <= 0 || $carbonDate->year > 2100) {
                    return null;
                }
                return $carbonDate->format('Y-m-d');
            }
        } catch (Exception $e) {
            logger()->error('Error parsing date for comparison', [
                'date' => $date,
                'error' => $e->getMessage()
            ]);
            // If parsing fails, treat as null
            return null;
        }

        return null;
    }

    /**
     * Get the field type for proper display handling.
     */
    private function getFieldType(string $field): string
    {
        if (in_array($field, ['emails', 'phones'])) {
            return 'array';
        }

        if ($field === 'address') {
            return 'address';
        }

        return 'text';
    }

}
