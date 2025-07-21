<?php

namespace Webkul\Admin\Http\Controllers\Contact\Persons;

use DateTime;
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
        $request->validate([
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'emails' => ['nullable', 'array'],
            'emails.*.value' => ['nullable', new EmailValidator()],
            'emails.*.label' => ['nullable', 'string'],
            'phones' => ['nullable', 'array'],
            'phones.*.value' => ['nullable', new PhoneValidator()],
            'phones.*.label' => ['nullable', 'string'],
            'date_of_birth' => ['nullable', new DateValidator()],
        ]);
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
            $data['phones'] = array_filter($data['phones'], function($phone) {
                return isset($phone['value']) && !empty(trim($phone['value']));
            });

            // If no valid phones remain, set to empty array
            if (empty($data['phones'])) {
                $data['phones'] = [];
            }
        }

        // Filter out empty email addresses
        if (isset($data['emails']) && is_array($data['emails'])) {
            $data['emails'] = array_filter($data['emails'], function($email) {
                return isset($email['value']) && !empty(trim($email['value']));
            });

            // If no valid emails remain, set to empty array
            if (empty($data['emails'])) {
                $data['emails'] = [];
            }
        }

        // Normaliseer is_default naar boolean voor phones
        if (isset($data['phones']) && is_array($data['phones'])) {
            $data['phones'] = array_map(function($phone) {
                if (isset($phone['is_default'])) {
                    $phone['is_default'] = $phone['is_default'] === true || $phone['is_default'] === 'on' || $phone['is_default'] === '1';
                }
                return $phone;
            }, $data['phones']);
        }
        // Normaliseer is_default naar boolean voor emails
        if (isset($data['emails']) && is_array($data['emails'])) {
            $data['emails'] = array_map(function($email) {
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
        $person = $this->personRepository->findOrFail($id);

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
        $request->validate([
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'emails' => ['nullable', 'array'],
            'emails.*.value' => ['nullable', new EmailValidator()],
            'emails.*.label' => ['nullable', 'string'],
            'phones' => ['nullable', 'array'],
            'phones.*.value' => ['nullable', new PhoneValidator()],
            'phones.*.label' => ['nullable', 'string'],
            'date_of_birth' => ['nullable', new DateValidator()],
        ]);
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
            $data['phones'] = array_filter($data['phones'], function($phone) {
                return isset($phone['value']) && !empty(trim($phone['value']));
            });

            // If no valid phones remain, set to empty array
            if (empty($data['phones'])) {
                $data['phones'] = [];
            }
        }

        // Filter out empty email addresses
        if (isset($data['emails']) && is_array($data['emails'])) {
            $data['emails'] = array_filter($data['emails'], function($email) {
                return isset($email['value']) && !empty(trim($email['value']));
            });

            // If no valid emails remain, set to empty array
            if (empty($data['emails'])) {
                $data['emails'] = [];
            }
        }

        // Normaliseer is_default naar boolean voor phones
        if (isset($data['phones']) && is_array($data['phones'])) {
            $data['phones'] = array_map(function($phone) {
                if (isset($phone['is_default'])) {
                    $phone['is_default'] = $phone['is_default'] === true || $phone['is_default'] === 'on' || $phone['is_default'] === '1';
                }
                return $phone;
            }, $data['phones']);
        }
        // Normaliseer is_default naar boolean voor emails
        if (isset($data['emails']) && is_array($data['emails'])) {
            $data['emails'] = array_map(function($email) {
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
                ->findWhereIn('user_id', $userIds);
        } else {
            $persons = $this->personRepository
                ->pushCriteria(app(RequestCriteria::class))
                ->all();
        }

        return PersonResource::collection($persons);
    }

    /**
     * Search person results based on lead data with scoring.
     */
    public function searchByLead(Lead $lead): JsonResource
    {
        $persons = Person::all();
        $scoredResults = [];

        foreach ($persons as $person) {
            $score = $this->calculateMatchScore($lead, $person);
            
            if ($score > 0) {
                $personData = $person->toArray();
                $personData['match_score'] = $score;
                $personData['match_score_percentage'] = round($score, 1);
                $scoredResults[] = $personData;
            }
        }

        // Sort by score descending
        usort($scoredResults, function ($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });

        // Convert back to collection and limit results
        $limitedResults = array_slice($scoredResults, 0, 10);
        
        // Create Person objects with additional score data
        $personsWithScores = collect($limitedResults)->map(function ($personData) {
            $person = new Person($personData);
            $person->id = $personData['id'];
            $person->match_score = $personData['match_score'];
            $person->match_score_percentage = $personData['match_score_percentage'];
            $person->exists = true; // Mark as existing model
            return $person;
        });

        return PersonResource::collection($personsWithScores);
    }

    /**
     * Calculate match score between lead and person.
     */
    private function calculateMatchScore(Lead $lead, Person $person): float
    {
        $score = 0.0;
        $maxScore = 100.0;

        // First name matching (20% weight)
        if (!empty($lead->first_name) && !empty($person->first_name)) {
            if (strtolower($lead->first_name) === strtolower($person->first_name)) {
                $score += 20.0;
            } elseif (stripos($person->first_name, $lead->first_name) !== false || 
                      stripos($lead->first_name, $person->first_name) !== false) {
                $score += 10.0;
            }
        }

        // Last name matching (20% weight)
        if (!empty($lead->last_name) && !empty($person->last_name)) {
            if (strtolower($lead->last_name) === strtolower($person->last_name)) {
                $score += 20.0;
            } elseif (stripos($person->last_name, $lead->last_name) !== false || 
                      stripos($lead->last_name, $person->last_name) !== false) {
                $score += 10.0;
            }
        }

        // Married name matching (15% weight)
        if (!empty($lead->married_name) && !empty($person->married_name)) {
            if (strtolower($lead->married_name) === strtolower($person->married_name)) {
                $score += 15.0;
            } elseif (stripos($person->married_name, $lead->married_name) !== false || 
                      stripos($lead->married_name, $person->married_name) !== false) {
                $score += 7.5;
            }
        }

        // Email matching (25% weight - highest since emails are usually unique)
        $emailScore = $this->calculateEmailMatchScore($lead, $person);
        $score += $emailScore * 0.25 * 100;

        // Phone number matching (20% weight)
        $phoneScore = $this->calculatePhoneMatchScore($lead, $person);
        $score += $phoneScore * 0.20 * 100;

        return min($score, $maxScore);
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

        // Handle contact_numbers field for persons
        if (!empty($entity->contact_numbers) && is_array($entity->contact_numbers)) {
            foreach ($entity->contact_numbers as $phone) {
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
}
