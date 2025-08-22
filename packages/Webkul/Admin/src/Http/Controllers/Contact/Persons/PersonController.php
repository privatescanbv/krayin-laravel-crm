<?php

namespace Webkul\Admin\Http\Controllers\Contact\Persons;

use App\Models\Address;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Prettus\Repository\Criteria\RequestCriteria;
use Webkul\Admin\DataGrids\Contact\PersonDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\AttributeForm;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Resources\PersonResource;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;

use Webkul\Core\Contracts\Validations\EmailValidator;
use Webkul\Core\Contracts\Validations\PhoneValidator;
use App\Validators\DateValidator;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;
use App\Services\PersonValidationService;

class PersonController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct(
        protected PersonRepository  $personRepository,
        private LeadRepository      $leadRepository,
        private AttributeRepository $attributeRepository)
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
        // Normalize contact arrays before validation
        $this->normalizeContactArrays($request);

        // Normalize contact arrays before validation
        $this->normalizeContactArrays($request);
        $request->validate(PersonValidationService::getWebValidationRules($request));
        Event::dispatch('contacts.person.create.before');

        $data = $request->all();
        $data['entity_type'] = 'persons';

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

        // Filter out empty phone numbers
        if (isset($data['phones']) && is_array($data['phones'])) {
            $data['phones'] = array_filter($data['phones'], function ($phone) {
                return isset($phone['value']) && !empty(trim($phone['value']));
            });

            // If no valid phones remain, set to empty array
            if (empty($data['phones'])) {
                $data['phones'] = [];
            }
        }

        // Filter out empty email addresses
        if (isset($data['emails']) && is_array($data['emails'])) {
            $data['emails'] = array_filter($data['emails'], function ($email) {
                return isset($email['value']) && !empty(trim($email['value']));
            });

            // If no valid emails remain, set to empty array
            if (empty($data['emails'])) {
                $data['emails'] = [];
            }
        }

        // Normaliseer is_default naar boolean voor phones
        if (isset($data['phones']) && is_array($data['phones'])) {
            $data['phones'] = array_map(function ($phone) {
                if (isset($phone['is_default'])) {
                    $phone['is_default'] = $phone['is_default'] === true || $phone['is_default'] === 'on' || $phone['is_default'] === '1';
                }
                return $phone;
            }, $data['phones']);
        }
        // Normaliseer is_default naar boolean voor emails
        if (isset($data['emails']) && is_array($data['emails'])) {
            $data['emails'] = array_map(function ($email) {
                if (isset($email['is_default'])) {
                    $email['is_default'] = $email['is_default'] === true || $email['is_default'] === 'on' || $email['is_default'] === '1';
                }
                return $email;
            }, $data['emails']);
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

        return view('admin::contacts.persons.view', compact('person'));
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
        // Normalize contact arrays before validation
        $this->normalizeContactArrays($request);

        $request->validate(PersonValidationService::getWebValidationRules($request));
        // Normalize contact arrays before validation
        $this->normalizeContactArrays($request);
        Event::dispatch('contacts.person.update.before', $id);

        $data = $request->all();
        $data['entity_type'] = 'persons';

        // Filter out entity field if present
        if (isset($data['entity'])) {
            unset($data['entity']);
        }

        // Debug: Log the incoming data
        Log::info('Person update request data:', $data);

        // Handle empty date fields
        if (isset($data['date_of_birth']) && empty($data['date_of_birth'])) {
            $data['date_of_birth'] = null;
        }

        // Handle empty unique_id field - convert empty string to null to avoid duplicate key errors
        if (isset($data['unique_id']) && empty($data['unique_id'])) {
            $data['unique_id'] = null;
        }

        // Filter out empty phone numbers
        if (isset($data['phones']) && is_array($data['phones'])) {
            $data['phones'] = array_filter($data['phones'], function ($phone) {
                return isset($phone['value']) && !empty(trim($phone['value']));
            });

            // If no valid phones remain, set to empty array
            if (empty($data['phones'])) {
                $data['phones'] = [];
            }
        }

        // Filter out empty email addresses
        if (isset($data['emails']) && is_array($data['emails'])) {
            $data['emails'] = array_filter($data['emails'], function ($email) {
                return isset($email['value']) && !empty(trim($email['value']));
            });

            // If no valid emails remain, set to empty array
            if (empty($data['emails'])) {
                $data['emails'] = [];
            }
        }

        // Normaliseer is_default naar boolean voor phones
        if (isset($data['phones']) && is_array($data['phones'])) {
            $data['phones'] = array_map(function ($phone) {
                if (isset($phone['is_default'])) {
                    $phone['is_default'] = $phone['is_default'] === true || $phone['is_default'] === 'on' || $phone['is_default'] === '1';
                }
                return $phone;
            }, $data['phones']);
        }
        // Normaliseer is_default naar boolean voor emails
        if (isset($data['emails']) && is_array($data['emails'])) {
            $data['emails'] = array_map(function ($email) {
                if (isset($email['is_default'])) {
                    $email['is_default'] = $email['is_default'] === true || $email['is_default'] === 'on' || $email['is_default'] === '1';
                }
                return $email;
            }, $data['emails']);
        }

        // Debug: Log the processed data
        Log::info('Person update processed data:', $data);

        $person = $this->personRepository->update($data, $id);

        Event::dispatch('contacts.person.update.after', $person);

        if (request()->ajax()) {
            return response()->json([
                'data' => $person,
                'message' => trans('admin::app.contacts.persons.index.update-success'),
            ], 200);
        }

        session()->flash('success', trans('admin::app.contacts.persons.index.update-success'));

        return redirect()->route('admin.contacts.persons.index');
    }

    /**
     * Search person results.
     */
    public function search(): JsonResource
    {
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
                    if (app()->environment('testing')) {
                        \Log::info('Person being scored', [
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
     * Calculate match score between lead and person.
     */
    private function calculateMatchScore(Lead $lead, Person $person): float
    {
        $score = 0.0;
        $maxScore = 100.0;

        // Calculate name field matches (including date of birth)
        $nameScore = $this->calculateNameMatchScore($lead, $person);

        // Email matching (5% weight)
        $emailScore = $this->calculateEmailMatchScore($lead, $person);
        $score += $emailScore * 0.05 * 100;

        // Phone number matching (5% weight)
        $phoneScore = $this->calculatePhoneMatchScore($lead, $person);
        $score += $phoneScore * 0.05 * 100;

        // Address matching (5% weight)
        $addressScore = $this->calculateAddressMatchScore($lead, $person);
        $score += $addressScore * 0.05 * 100;

        // Add name score (85% total weight)
        $score += $nameScore * 0.85 * 100;

        // Debug logging for tests - always log for person ID 1 in tests
        if (app()->environment('testing') && ($person->id == 1 || $lead->id == 1)) {
            \Log::info('Match Score Debug', [
                'lead_id' => $lead->id,
                'person_id' => $person->id,
                'nameScore' => $nameScore,
                'emailScore' => $emailScore,
                'phoneScore' => $phoneScore,
                'addressScore' => $addressScore,
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

        // Debug score after empty fields fix
        if (app()->environment('testing') && $person->id == 1) {
            \Log::info('Score After Empty Fields Fix', [
                'nameScore' => $nameScore,
                'emailScore' => $emailScore, 
                'phoneScore' => $phoneScore,
                'addressScore' => $addressScore,
                'finalScore' => min($score, $maxScore),
                'calculation' => [
                    'name_contribution' => $nameScore * 0.85 * 100,
                    'email_contribution' => $emailScore * 0.05 * 100,
                    'phone_contribution' => $phoneScore * 0.05 * 100,
                    'address_contribution' => $addressScore * 0.05 * 100,
                ]
            ]);
        }

        return min($score, $maxScore);
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
                } else {
                    Log::info("Name field '{$field}' did not match", [
                        'lead_value' => $leadValue,
                        'person_value' => $personValue
                    ]);
                }
            }
        }

        // Calculate scores based on new criteria
        if ($totalPossibleMatches === 0) {
            return 0.0;
        }

        $totalMatchRatio = $totalMatches / $totalPossibleMatches;

        // Debug name matching for person ID 1
        if (app()->environment('testing') && $person->id == 1) {
            \Log::info('Name Match Debug', [
                'totalMatches' => $totalMatches,
                'totalPossibleMatches' => $totalPossibleMatches,
                'importantMatches' => $importantMatches,
                'importantPossibleMatches' => $importantPossibleMatches,
                'totalMatchRatio' => $totalMatchRatio,
                'will_return_score' => $totalMatchRatio === 1.0 ? 0.95 : ($importantPossibleMatches > 0 && $importantMatches === $importantPossibleMatches ? 0.80 : $totalMatchRatio * 0.80)
            ]);
        }

        // 100% match on all name fields = 95% score
        if ($totalMatchRatio >= 1.0) {
            return 0.95;
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
        if (app()->environment('testing') && $person->id == 1) {
            \Log::info('Phone Match Debug', [
                'lead_id' => $lead->id,
                'person_id' => $person->id,
                'leadPhones' => $leadPhones,
                'personPhones' => $personPhones,
                'lead_phones_raw' => $lead->phones,
                'person_phones_raw' => $person->phones,
            ]);
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
        if (app()->environment('testing') && $person->id == 1) {
            \Log::info('Address Match Debug', [
                'lead_id' => $lead->id,
                'person_id' => $person->id,
                'leadAddress' => $leadAddress,
                'personAddress' => $personAddress,
                'leadAddressEmpty' => empty($leadAddress),
                'personAddressEmpty' => empty($personAddress),
            ]);
        }

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
        // Handle null/empty comparisons
        $value1 = $value1 === null ? '' : $value1;
        $value2 = $value2 === null ? '' : $value2;

        return trim($value1) !== trim($value2);
    }

    /**
     * Normalize contact arrays to ensure proper data types
     */
    private function normalizeContactArrays($request)
    {
        $requestData = $request->all();

        // Normalize emails
        if (isset($requestData['emails']) && is_array($requestData['emails'])) {
            foreach ($requestData['emails'] as $index => $email) {
                if (is_array($email)) {
                    // Ensure label exists and normalize it
                    if (!isset($email['label']) || empty($email['label'])) {
                        $requestData['emails'][$index]['label'] = 'work';
                    } else {
                        $requestData['emails'][$index]['label'] = $this->normalizeLabel($email['label']);
                    }

                    // Normalize is_default to boolean
                    if (isset($email['is_default'])) {
                        $requestData['emails'][$index]['is_default'] = $this->normalizeBoolean($email['is_default']);
                    } else {
                        $requestData['emails'][$index]['is_default'] = false;
                    }
                }
            }
        }

        // Normalize phones
        if (isset($requestData['phones']) && is_array($requestData['phones'])) {
            foreach ($requestData['phones'] as $index => $phone) {
                if (is_array($phone)) {
                    // Ensure label exists and normalize it
                    if (!isset($phone['label']) || empty($phone['label'])) {
                        $requestData['phones'][$index]['label'] = 'work';
                    } else {
                        $requestData['phones'][$index]['label'] = $this->normalizeLabel($phone['label']);
                    }

                    // Normalize is_default to boolean
                    if (isset($phone['is_default'])) {
                        $requestData['phones'][$index]['is_default'] = $this->normalizeBoolean($phone['is_default']);
                    } else {
                        $requestData['phones'][$index]['is_default'] = false;
                    }
                }
            }
        }

        // Normalize contact_numbers (for backwards compatibility)
        if (isset($requestData['contact_numbers']) && is_array($requestData['contact_numbers'])) {
            foreach ($requestData['contact_numbers'] as $index => $phone) {
                if (is_array($phone)) {
                    // Ensure label exists and normalize it
                    if (!isset($phone['label']) || empty($phone['label'])) {
                        $requestData['contact_numbers'][$index]['label'] = 'work';
                    } else {
                        $requestData['contact_numbers'][$index]['label'] = $this->normalizeLabel($phone['label']);
                    }

                    // Normalize is_default to boolean
                    if (isset($phone['is_default'])) {
                        $requestData['contact_numbers'][$index]['is_default'] = $this->normalizeBoolean($phone['is_default']);
                    } else {
                        $requestData['contact_numbers'][$index]['is_default'] = false;
                    }
                }
            }
        }

        // Replace the request data
        $request->replace($requestData);
    }

    /**
     * Normalize various representations to boolean
     */
    private function normalizeBoolean($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'on', 'yes']);
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        return false;
    }

    /**
     * Normalize label to lowercase and handle common variations
     */
    private function normalizeLabel(string $label): string
    {
        if (empty($label)) {
            return 'work';
        }

        // Convert to lowercase and map common variations
        $normalizedLabel = strtolower(trim($label));
        $labelMap = [
            'work' => 'work',
            'werk' => 'work',
            'home' => 'home',
            'thuis' => 'home',
            'mobile' => 'mobile',
            'mobiel' => 'mobile',
            'other' => 'other',
            'anders' => 'other'
        ];

        return $labelMap[$normalizedLabel] ?? 'work';
    }
}
