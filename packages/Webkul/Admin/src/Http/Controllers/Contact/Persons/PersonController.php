<?php

namespace Webkul\Admin\Http\Controllers\Contact\Persons;

use App\Enums\ContactLabel;
use App\Http\Controllers\Concerns\NormalizesContactFields;
use App\Models\Address;
use BackedEnum;
use Dotenv\Exception\ValidationException;
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
        if (isset($data['salutation']) && $data['salutation'] instanceof \BackedEnum) {
            $data['salutation'] = $data['salutation']->value;
        }
        if (isset($data['gender']) && $data['gender'] instanceof \BackedEnum) {
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

            $result = $this->buildMatchBreakdown($lead, $person);

            return response()->json(array_merge([
                'lead_id' => $lead->id,
                'person_id' => $person->id,
            ], $result['breakdown_detailed']));
        } catch (\Throwable $e) {
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

            $result = $this->buildMatchBreakdown($lead, $person);

            return response()->json([
                'person' => [
                    'id' => $person->id,
                    'name' => $person->name,
                    'match_score_percentage' => $result['percentage'],
                ],
                'breakdown' => $result['breakdown'],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Unable to calculate match score',
            ], 400);
        }
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
    private function buildMatchBreakdown(Lead $lead, Person $person): array
    {
        $nameScore = $this->calculateNameMatchScore($lead, $person); // 0..1
        $emailScore = $this->calculateEmailMatchScore($lead, $person); // 0..1
        $phoneScore = $this->calculatePhoneMatchScore($lead, $person); // 0..1
        $addressScore = $this->calculateAddressMatchScore($lead, $person); // 0..1

        $finalScore = min(
            ($nameScore * 0.85 + $emailScore * 0.05 + $phoneScore * 0.05 + $addressScore * 0.05) * 100,
            100
        );

        return [
            'percentage' => round($finalScore, 1),
            'breakdown' => [
                'name' => [
                    'ratio' => $nameScore,
                    'weighted' => $nameScore * 0.85 * 100,
                    'weight' => 0.85,
                ],
                'email' => [
                    'matched' => ($emailScore ?? 0) > 0,
                    'weighted' => $emailScore * 0.05 * 100,
                    'weight' => 0.05,
                ],
                'phone' => [
                    'matched' => ($phoneScore ?? 0) > 0,
                    'weighted' => $phoneScore * 0.05 * 100,
                    'weight' => 0.05,
                ],
                'address' => [
                    'matched' => ($addressScore ?? 0) > 0,
                    'weighted' => $addressScore * 0.05 * 100,
                    'weight' => 0.05,
                ],
                'final' => [
                    'score' => $finalScore,
                ],
            ],
            // Detailed variant used by matchScoreBreakdown (includes field values)
            'breakdown_detailed' => [
                'name' => [
                    'ratio' => $nameScore,
                    'weighted' => $nameScore * 0.85 * 100,
                    'weight' => 0.85,
                    'fields' => [
                        'first_name' => [$lead->first_name ?? null, $person->first_name ?? null],
                        'last_name' => [$lead->last_name ?? null, $person->last_name ?? null],
                        'lastname_prefix' => [$lead->lastname_prefix ?? null, $person->lastname_prefix ?? null],
                        'married_name' => [$lead->married_name ?? null, $person->married_name ?? null],
                        'married_name_prefix' => [$lead->married_name_prefix ?? null, $person->married_name_prefix ?? null],
                        'initials' => [$lead->initials ?? null, $person->initials ?? null],
                        'date_of_birth' => [$lead->date_of_birth ?? null, $person->date_of_birth ?? null],
                    ],
                ],
                'email' => [
                    'matched' => ($emailScore ?? 0) > 0,
                    'weighted' => $emailScore * 0.05 * 100,
                    'weight' => 0.05,
                    'lead' => $lead->emails ?? [],
                    'person' => $person->emails ?? [],
                ],
                'phone' => [
                    'matched' => ($phoneScore ?? 0) > 0,
                    'weighted' => $phoneScore * 0.05 * 100,
                    'weight' => 0.05,
                    'lead' => $lead->phones ?? [],
                    'person' => $person->phones ?? [],
                ],
                'address' => [
                    'matched' => ($addressScore ?? 0) > 0,
                    'weighted' => $addressScore * 0.05 * 100,
                    'weight' => 0.05,
                    'lead' => [
                        'street' => $lead->address->street ?? null,
                        'house_number' => $lead->address->house_number ?? null,
                        'house_number_suffix' => $lead->address->house_number_suffix ?? null,
                        'postal_code' => $lead->address->postal_code ?? null,
                        'city' => $lead->address->city ?? null,
                        'country' => $lead->address->country ?? null,
                    ],
                    'person' => [
                        'street' => $person->address->street ?? null,
                        'house_number' => $person->address->house_number ?? null,
                        'house_number_suffix' => $person->address->house_number_suffix ?? null,
                        'postal_code' => $person->address->postal_code ?? null,
                        'city' => $person->address->city ?? null,
                        'country' => $person->address->country ?? null,
                    ],
                ],
                'final' => [
                    'score' => $finalScore,
                ],
            ],
        ];
    }

    /**
     * Calculate match score between lead and person.
     */
    public function calculateMatchScore(Lead $lead, Person $person): float
    {
        $maxScore = 100.0;

        $result = $this->buildMatchBreakdown($lead, $person);
        $score = $result['percentage'];

        // Debug logging for tests - always log for person ID 1 in tests
        if ($this->enableLogging && ($lead->id == 9 && $person->id == 3)) {
            Log::info('Match Score Debug', [
                'lead_id' => $lead->id,
                'person_id' => $person->id,
                'nameScore' => $result['breakdown']['name']['ratio'] ?? null,
                'emailScore' => ($result['breakdown']['email']['weighted'] ?? 0) / 5,
                'phoneScore' => ($result['breakdown']['phone']['weighted'] ?? 0) / 5,
                'addressScore' => ($result['breakdown']['address']['weighted'] ?? 0) / 5,
                'finalScore' => min($score, $maxScore),
                'lead_data' => [
                    'first_name' => $lead->first_name,
                    'last_name' => $lead->last_name,
                    'lastname_prefix' => $lead->lastname_prefix,
                    'emails' => $lead->emails,
                    'phones' => $lead->phones,
                ],
                'person_data' => [
                    'first_name' => $person->first_name,
                    'last_name' => $person->last_name,
                    'lastname_prefix' => $person->lastname_prefix,
                    'emails' => $person->emails,
                    'phones' => $person->phones,
                ]
            ]);
        }

        return min((float) $score, $maxScore);
    }

    /**
     * Calculate name match score with new logic.
     */
    private function calculateNameMatchScore(Lead $lead, Person $person): float
    {
        $nameFields = [
            'first_name',
            'last_name',
            'lastname_prefix',
            'married_name',
            'married_name_prefix',
            'initials',
            'date_of_birth'
        ];

        $importantNameFields = [
            'first_name',
            'last_name',
            'lastname_prefix'
        ];

        $totalMatches = 0;
        $totalPossibleMatches = 0;
        $importantMatches = 0;
        $importantPossibleMatches = 0;

        foreach ($nameFields as $field) {
            $leadValue = $lead->$field ?? '';
            $personValue = $person->$field ?? '';

            if (!empty($leadValue) || !empty($personValue)) {
                $totalPossibleMatches++;

                if (in_array($field, $importantNameFields)) {
                    $importantPossibleMatches++;
                }

                // Handle matching logic
                $isMatch = false;

                // If both values are empty, treat as match
                if (empty($leadValue) && empty($personValue)) {
                    $isMatch = true;
                }
                // If both values exist, compare them
                elseif (!empty($leadValue) && !empty($personValue)) {
                    // Special handling for date_of_birth
                    if ($field === 'date_of_birth') {
                        $leadDate = $this->formatDateForComparison($leadValue);
                        $personDate = $this->formatDateForComparison($personValue);
                        if ($leadDate && $personDate && $leadDate === $personDate) {
                            $isMatch = true;
                        }
                    }
                    // Exact match for other fields
                    elseif (strtolower(trim($leadValue)) === strtolower(trim($personValue))) {
                        $isMatch = true;
                    }
                    // Partial match for names (not for initials or date_of_birth)
                    elseif (!in_array($field, ['initials', 'date_of_birth']) &&
                        (stripos($personValue, $leadValue) !== false ||
                            stripos($leadValue, $personValue) !== false)) {
                        $isMatch = true;
                    }
                }
                // If one is empty and one is not, no match (default $isMatch = false)

                if ($isMatch) {
                    $totalMatches++;
                    if (in_array($field, $importantNameFields)) {
                        $importantMatches++;
                    }
                }
//                // can be used for debugging specific name field matches
//                else {
//                    Log::info("Name field '{$field}' did not match", [
//                        'lead_value' => $leadValue,
//                        'person_value' => $personValue
//                    ]);
//                }
            }
        }

        // Calculate scores based on new criteria
        if ($totalPossibleMatches === 0) {
            return 0.0;
        }

        $totalMatchRatio = $totalMatches / $totalPossibleMatches;

        // Debug name matching for person ID 1
        if ($this->enableLogging&& $person->id == 1) {
            Log::info('Name Match Debug', [
                'totalMatches' => $totalMatches,
                'totalPossibleMatches' => $totalPossibleMatches,
                'importantMatches' => $importantMatches,
                'importantPossibleMatches' => $importantPossibleMatches,
                'totalMatchRatio' => $totalMatchRatio,
                'will_return_score' => $totalMatchRatio === 1.0 ? 0.95 : ($importantPossibleMatches > 0 && $importantMatches === $importantPossibleMatches ? 0.80 : $totalMatchRatio * 0.80)
            ]);
        }

        // 100% match on all name fields = 100% score for perfect test matches
        if ($totalMatchRatio >= 1.0) {
            return 1.0;
        }

        // 100% match on important name fields = 80% score
        if ($importantPossibleMatches > 0 && $importantMatches === $importantPossibleMatches && $totalMatchRatio < 1.0) {
            return 0.80;
        }

        // Partial scoring based on match ratio
        // Scale between 0 and 0.80 based on total match ratio
        return $totalMatchRatio * 0.80;
    }

    /**
     * Calculate email match score between lead and person.
     */
    private function calculateEmailMatchScore(Lead $lead, Person $person): float
    {
        $leadEmails = $this->extractEmails($lead);
        $personEmails = $this->extractEmails($person);

        if (empty($leadEmails) || empty($personEmails)) {
            return 0.0;
        }

        $matchCount = 0;
        $totalPersonEmails = count($personEmails);

        foreach ($leadEmails as $leadEmail) {
            foreach ($personEmails as $personEmail) {
                if (strtolower($leadEmail) === strtolower($personEmail)) {
                    $matchCount++;
                    break; // Don't count the same lead email multiple times
                }
            }
        }

        // If all person emails match, return 1.0
        // If some match, return partial score
        return $matchCount > 0 ? ($matchCount / $totalPersonEmails) : 0.0;
    }

    /**
     * Calculate phone match score between lead and person.
     */
    private function calculatePhoneMatchScore(Lead $lead, Person $person): float
    {
        $leadPhones = $this->extractPhones($lead);
        $personPhones = $this->extractPhones($person);

        // Debug phone extraction for person ID 1
        if ($this->enableLogging&& $person->id == 1) {
            Log::info('Phone Match Debug', [
                'lead_id' => $lead->id,
                'person_id' => $person->id,
                'leadPhones' => $leadPhones,
                'personPhones' => $personPhones,
                'lead_phones_raw' => $lead->phones,
                'person_phones_raw' => $person->phones,
            ]);
        }

        // Treat both empty as perfect match; one empty as no match
        if (empty($leadPhones) && empty($personPhones)) {
            return 1.0;
        }
        if (empty($leadPhones) || empty($personPhones)) {
            return 0.0;
        }

        $matchCount = 0;
        $totalPersonPhones = count($personPhones);

        foreach ($leadPhones as $leadPhone) {
            foreach ($personPhones as $personPhone) {
                if ($this->normalizePhoneNumber($leadPhone) === $this->normalizePhoneNumber($personPhone)) {
                    $matchCount++;
                    break; // Don't count the same lead phone multiple times
                }
            }
        }

        return $matchCount > 0 ? ($matchCount / $totalPersonPhones) : 0.0;
    }

    /**
     * Calculate address match score between lead and person.
     */
    private function calculateAddressMatchScore(Lead $lead, Person $person): float
    {
        $leadAddress = $this->extractAddressData($lead);
        $personAddress = $this->extractAddressData($person);

        // Debug address extraction for person ID 1
        if ($this->enableLogging&& $person->id == 1) {
            Log::info('Address Match Debug', [
                'lead_id' => $lead->id,
                'person_id' => $person->id,
                'leadAddress' => $leadAddress,
                'personAddress' => $personAddress,
                'leadAddressEmpty' => empty($leadAddress),
                'personAddressEmpty' => empty($personAddress),
            ]);
        }

        // If both addresses are empty, treat as perfect match (like empty fields fix)
        if (empty($leadAddress) && empty($personAddress)) {
            return 1.0;
        }

        // If only one address is empty, no match
        if (empty($leadAddress) || empty($personAddress)) {
            return 0.0;
        }

        $addressFields = ['street', 'house_number', 'city', 'postal_code', 'country'];
        $matchCount = 0;
        $totalFields = 0;

        foreach ($addressFields as $field) {
            $leadValue = $leadAddress[$field] ?? '';
            $personValue = $personAddress[$field] ?? '';

            if (!empty($leadValue) || !empty($personValue)) {
                $totalFields++;

                if (!empty($leadValue) && !empty($personValue)) {
                    // Normalize and compare
                    $leadNormalized = strtolower(trim($leadValue));
                    $personNormalized = strtolower(trim($personValue));

                    if ($leadNormalized === $personNormalized) {
                        $matchCount++;
                    }
                    // For postal codes, also check partial matches (useful for Dutch postal codes)
                    elseif ($field === 'postal_code' &&
                           (strpos($leadNormalized, $personNormalized) !== false ||
                            strpos($personNormalized, $leadNormalized) !== false)) {
                        $matchCount += 0.5; // Partial match
                    }
                }
            }
        }

        return $totalFields > 0 ? ($matchCount / $totalFields) : 0.0;
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
     * Extract address data from lead or person.
     */
    private function extractAddressData($entity): array
    {
        $address = [];

        // For both persons and leads, check if they have an address relationship
        if (method_exists($entity, 'address') && $entity->address) {
            $address = [
                'street' => $entity->address->street ?? '',
                'house_number' => $entity->address->house_number ?? '',
                'city' => $entity->address->city ?? '',
                'postal_code' => $entity->address->postal_code ?? '',
                'country' => $entity->address->country ?? '',
            ];
        }
        // Fallback to direct address fields (for backwards compatibility)
        else {
            $address = [
                'street' => $entity->street ?? '',
                'house_number' => $entity->house_number ?? '',
                'city' => $entity->city ?? '',
                'postal_code' => $entity->postal_code ?? '',
                'country' => $entity->country ?? '',
            ];
        }

        // Filter out empty values
        return array_filter($address, function($value) {
            return !empty(trim($value));
        });
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
        } catch (\Exception $exception) {
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
     * Show the form for updating person with lead data.
     */
    public function editWithLead(int $personId, int $leadId): View
    {
        $person = $this->personRepository->findOrFail($personId);
        $lead = app(LeadRepository::class)->findOrFail($leadId);

        $fieldDifferences = $this->comparePersonWithLead($person, $lead);

        return view('admin::contacts.persons.edit-with-lead', compact('person', 'lead', 'fieldDifferences'));
    }

    /**
     * Update person with selected lead data.
     */
    public function updateWithLead(int $personId, int $leadId)
    {
        $person = $this->personRepository->findOrFail($personId);
        $lead = app(LeadRepository::class)->findOrFail($leadId);

        $data = request()->all();
        $leadUpdates = $data['lead_updates'] ?? [];
        $personUpdates = $data['person_updates'] ?? [];

        try {
            // Update lead with modified values
            if (!empty($leadUpdates)) {
                $lead->update($leadUpdates);
            }

            // Update person with selected values from lead
            if (!empty($personUpdates)) {
                $updateData = [];
                foreach ($personUpdates as $field => $shouldUpdate) {
                    if ($shouldUpdate) {
                        if (isset($leadUpdates[$field])) {
                            // Use the potentially modified lead value
                            $value = $leadUpdates[$field];
                        } else {
                            // Use the original lead value
                            $value = $lead->$field;
                        }

                        // Handle array fields (emails, phones) - convert string back to array format
                        if (in_array($field, ['emails', 'phones']) && is_string($value)) {
                            $values = array_filter(explode(', ', $value));
                            $arrayData = [];
                            foreach ($values as $index => $val) {
                                $arrayData[] = [
                                    'value' => trim($val),
                                    'label' => $field === 'emails' ? 'Work' : 'Mobile',
                                    'is_default' => $index === 0
                                ];
                            }
                            $updateData[$field] = $arrayData;
                        } elseif ($field === 'address') {
                            // Handle address field - copy lead address to person
                            if ($lead->address) {
                                $this->copyAddressFromLeadToPerson($lead, $person);
                            }
                        } else {
                            $updateData[$field] = $value;
                        }
                    }
                }

                if (!empty($updateData)) {
                    $person->update($updateData);
                }
            }

            // Check if it's an AJAX request
            if (request()->expectsJson() || request()->ajax()) {
                return response()->json([
                    'message' => 'Person en lead succesvol bijgewerkt.',
                    'redirect_url' => route('admin.contacts.persons.view', $person->id)
                ]);
            }
            return redirect()->route('admin.contacts.persons.view', $person->id);

        } catch (Exception $e) {
            logger()->error('Could not sync person with lead ' . $e->getMessage(), [
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
     * Compare person and lead fields to find differences.
     */
    private function comparePersonWithLead(Person $person, Lead $lead): array
    {
                $comparableFields = [
            // Personal Information (fields that exist in both Person and Lead models)
            'salutation' => 'Aanhef',
            'first_name' => 'Voornaam',
            'last_name' => 'Achternaam',
            'lastname_prefix' => 'Voorvoegsel achternaam',
            'married_name' => 'Gehuwde naam',
            'married_name_prefix' => 'Voorvoegsel gehuwde naam',
            'initials' => 'Initialen',
            'date_of_birth' => 'Geboortedatum',
            'gender' => 'Geslacht',

            // Contact Information
            'emails' => 'E-mailadressen',
            'phones' => 'Telefoonnummers',

            // Address Information
            'address' => 'Adres',
        ];

        $differences = [];

        foreach ($comparableFields as $field => $label) {
            $personValue = $person->$field;
            $leadValue = $lead->$field;

            // Handle array fields (emails, phones)
            if (in_array($field, ['emails', 'phones'])) {
                $personValue = $this->normalizeArrayField($personValue);
                $leadValue = $this->normalizeArrayField($leadValue);
            }

            // Handle date fields
            if ($field === 'date_of_birth') {
                $personValue = $this->formatDateForComparison($personValue);
                $leadValue = $this->formatDateForComparison($leadValue);
            }

            // Handle address field
            if ($field === 'address') {
                $personValue = $this->normalizeAddressField($person->address);
                $leadValue = $this->normalizeAddressField($lead->address);
            }

            // Compare values
            if ($this->valuesAreDifferent($personValue, $leadValue)) {
                $differences[$field] = [
                    'label' => $label,
                    'person_value' => $personValue,
                    'lead_value' => $leadValue,
                    'type' => $this->getFieldType($field)
                ];
            }
        }

        return $differences;
    }

    /**
     * Normalize array fields for comparison.
     */
    private function normalizeArrayField($value): string
    {
        if (empty($value)) {
            return '';
        }

        if (is_array($value)) {
            $values = array_map(function ($item) {
                return is_array($item) ? ($item['value'] ?? '') : $item;
            }, $value);
            return implode(', ', array_filter($values));
        }

        return (string) $value;
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
            if ($date instanceof \Carbon\Carbon) {
                // Check for invalid dates (like -0001-11-30 or 0000-00-00)
                if ($date->year <= 0 || $date->year > 2100) {
                    return null;
                }
                return $date->format('Y-m-d');
            }

            // If it's a string, try to parse it
            if (is_string($date)) {
                // Skip obviously invalid dates
                if (in_array($date, ['0000-00-00', '0000-00-00 00:00:00']) || strpos($date, '-0001') === 0) {
                    return null;
                }

                $carbonDate = \Carbon\Carbon::parse($date);
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
     * Normalize address field for comparison.
     */
    private function normalizeAddressField($address): string
    {
        if (empty($address)) {
            return '';
        }

        // Create a normalized address string for comparison
        $addressParts = [
            $address->full_address ?? '',
            $address->street ?? '',
            $address->house_number ?? '',
            $address->house_number_suffix ?? '',
            $address->postal_code ?? '',
            $address->city ?? '',
            $address->state ?? '',
            $address->country ?? ''
        ];

        return implode('|', array_filter($addressParts));
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

    /**
     * Copy address from lead to person.
     */
    private function copyAddressFromLeadToPerson($lead, $person): void
    {
        if (!$lead->address) {
            return;
        }

        $addressData = [
            'street' => $lead->address->street,
            'house_number' => $lead->address->house_number,
            'house_number_suffix' => $lead->address->house_number_suffix,
            'postal_code' => $lead->address->postal_code,
            'city' => $lead->address->city,
            'state' => $lead->address->state,
            'country' => $lead->address->country,
            'person_id' => $person->id,
        ];

        // Delete existing address if it exists
        if ($person->address) {
            $person->address->delete();
        }

        // Create new address for person
        Address::create($addressData);
    }

    /**
     * Check if two values are different.
     */
    private function valuesAreDifferent($value1, $value2): bool
    {
        // Unwrap enums to their backing values
        if ($value1 instanceof \BackedEnum) {
            $value1 = $value1->value;
        }
        if ($value2 instanceof \BackedEnum) {
            $value2 = $value2->value;
        }

        // If either is an array, compare directly (no trim)
        if (is_array($value1) || is_array($value2)) {
            return $value1 !== $value2;
        }

        // Normalize nulls to empty strings and cast scalars to string
        $value1 = $value1 === null ? '' : (string) $value1;
        $value2 = $value2 === null ? '' : (string) $value2;

        return trim($value1) !== trim($value2);
    }

    /**
     * Normalize contact arrays to ensure proper data types
     * @deprecated Use normalizeContactFields() from NormalizesContactFields trait instead
     */
    private function normalizeContactArrays($request)
    {
        // Replaced by normalizeContactFields() from trait
        $this->normalizeContactFields($request);
    }
}
