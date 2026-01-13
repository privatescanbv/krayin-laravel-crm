<?php

namespace Webkul\Contact\Repositories;

use App\Enums\DuplicateEntityType;
use App\Models\Address;
use App\Services\Concerns\JsonDuplicateMatcher;
use App\Services\DuplicateFalsePositiveService;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Validator;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Contact\Contracts\Person;
use Webkul\Core\Eloquent\Repository;

class PersonRepository extends Repository
{
    use JsonDuplicateMatcher;

    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'name',
        'first_name',
        'last_name',
        'lastname_prefix',
        'married_name',
        'married_name_prefix',
        'initials',
        'emails',
        'phones', // Renamed from contact_numbers
        'organization_id',
        'organization.name',
        'user_id',
        'user.name',
    ];

    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        protected AttributeRepository $attributeRepository,
        protected AttributeValueRepository $attributeValueRepository,
        protected OrganizationRepository $organizationRepository,
        private readonly DuplicateFalsePositiveService $falsePositiveService,
        Container $container
    ) {
        parent::__construct($container);
    }

    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return Person::class;
    }

    /**
     * Create.
     *
     * @return \Webkul\Contact\Contracts\Person
     */
    public function create(array $data): Person
    {
        $data = $this->sanitizeRequestedPersonData($data);

        if (! empty($data['organization_name'])) {
            $organization = $this->fetchOrCreateOrganizationByName($data['organization_name']);

            $data['organization_id'] = $organization->id;
        }

        if (isset($data['user_id'])) {
            $data['user_id'] = $data['user_id'] ?: null;
        }

        $person = parent::create($data);

        $this->attributeValueRepository->save(array_merge($data, [
            'entity_id' => $person->id,
        ]));

                // Handle address data for new persons
        if (isset($data['address']) && !empty($data['address'])) {
            $addressData = $data['address'];
            // Check if there's any meaningful address data (not just empty strings)
            $hasAddressData = !empty(array_filter($addressData, function($value) {
                return !empty(trim($value));
            }));

            if ($hasAddressData) {
                // Add person_id to address data for validation
                $addressDataWithPersonId = array_merge($addressData, [
                    'person_id' => $person->id,
                ]);

                // Validate address data
                $validator = Validator::make($addressDataWithPersonId, Address::$rules);
                if ($validator->fails()) {
                    throw new InvalidArgumentException('Address validation failed: ' . $validator->errors()->first());
                }

                Address::create($addressDataWithPersonId);
            }
        }

        return $person;
    }

    /**
     * Update.
     *
     * @return \Webkul\Contact\Contracts\Person
     */
    public function update(array $data, $id, $attributes = []): Person
    {
        $data = $this->sanitizeRequestedPersonData($data);

        $data['user_id'] = empty($data['user_id']) ? null : $data['user_id'];

        if (! empty($data['organization_name'])) {
            $organization = $this->fetchOrCreateOrganizationByName($data['organization_name']);

            $data['organization_id'] = $organization->id;

            unset($data['organization_name']);
        }

        $person = parent::update($data, $id);

        /**
         * If attributes are provided then only save the provided attributes and return.
         */
        if (! empty($attributes)) {
            $conditions = ['entity_type' => $data['entity_type']];

            if (isset($data['quick_add'])) {
                $conditions['quick_add'] = 1;
            }

            $attributes = $this->attributeRepository->where($conditions)
                ->whereIn('code', $attributes)
                ->get();

            $this->attributeValueRepository->save(array_merge($data, [
                'entity_id' => $person->id,
            ]), $attributes);

            return $person;
        }

        $this->attributeValueRepository->save(array_merge($data, [
            'entity_id' => $person->id,
        ]));

        // Handle address data
        if (isset($data['address']) && !empty($data['address'])) {
            $addressData = $data['address'];
            // Check if there's any meaningful address data (not just empty strings)
            $filledAddressData = array_filter($addressData, function($value) {
                return !empty(trim($value));
            });
            $hasAddressData = !empty($filledAddressData);

            Log::info('Address filtering debug:', [
                'original_data' => $addressData,
                'filled_data' => $filledAddressData,
                'has_address_data' => $hasAddressData,
            ]);
            Log::info('Updating address ', [
                'address' => $addressData,
                'city_value' => $addressData['city'] ?? 'NOT_SET',
                'city_isset' => isset($addressData['city']),
                'city_empty' => empty($addressData['city'] ?? ''),
                'city_trimmed' => trim($addressData['city'] ?? ''),
            ]);
            if ($hasAddressData) {
                // Add person_id to address data for validation
                $addressDataWithPersonId = array_merge($addressData, [
                    'person_id' => $id,
                ]);

                // Validate address data
                $validator = Validator::make($addressDataWithPersonId, Address::$rules);
                if ($validator->fails()) {
                    throw new InvalidArgumentException('Address validation failed: ' . $validator->errors()->first());
                }

                // Check if person already has an address
                $existingAddress = Address::where('person_id', $id)->first();

                if ($existingAddress) {
                    // Update existing address - only update filled fields
                    $existingAddress->update($filledAddressData);
                } else {
                    // Create new address - use all data (including empty fields)
                    Address::create($addressDataWithPersonId);
                }
            }
        }

        return $person;
    }

    /**
     * Retrieves customers count based on date.
     *
     * @return int
     */
    public function getCustomerCount($startDate, $endDate)
    {
        return $this
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->count();
    }

    /**
     * Fetch or create an organization.
     */
    public function fetchOrCreateOrganizationByName(string $organizationName)
    {
        $organization = $this->organizationRepository->findOneWhere([
            'name' => $organizationName,
        ]);

        return $organization ?: $this->organizationRepository->create([
            'entity_type' => 'organizations',
            'name'        => $organizationName,
        ]);
    }

    public function resolveEmailVariablesById($personId): array {
        return $this->resolveEmailVariables($this->find($personId));
    }

    private function resolveEmailVariables(Person $person): array
    {
        return ['lastname' => $person->last_name];
    }

    /**
     * Sanitize requested person data and return the clean array.
     */
    private function sanitizeRequestedPersonData(array $data): array
    {
        if (
            array_key_exists('organization_id', $data)
            && empty($data['organization_id'])
        ) {
            $data['organization_id'] = null;
        }

        $uniqueIdParts = array_filter([
            $data['user_id'] ?? null,
            $data['organization_id'] ?? null,
            $data['emails'][0]['value'] ?? null,
        ]);

        $data['unique_id'] = implode('|', $uniqueIdParts);

        // Normalize portal activation flag (checkbox/switch can submit as on/1/true)
        $data['is_active'] = filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Drop empty password submissions to avoid overwriting existing hashes
        if (array_key_exists('password', $data) && empty($data['password'])) {
            unset($data['password']);
        }

        if (isset($data['phones'])) {
            $data['phones'] = collect($data['phones'])->filter(fn ($number) => ! is_null($number['value']))->toArray();

            if (!empty($data['phones'])) {
                $data['unique_id'] .= '|'.$data['phones'][0]['value'];
            }
        }

        // If unique_id is empty after generation, set it to null to avoid duplicate key errors
        if (empty($data['unique_id'])) {
            $data['unique_id'] = null;
        }

        return $data;
    }

    /**
     * Find potential duplicate persons based on email, phone, and name similarity.
     *
     * @param Person $person
     * @return Collection
     */
    public function findPotentialDuplicates($person): Collection
    {
        try {
            // Use direct method to avoid circular dependency
            $duplicates = $this->findPotentialDuplicatesDirectly($person);

            if ($duplicates->isEmpty()) {
                return collect();
            }

            $filteredIds = $this->falsePositiveService->filterCandidateIdsForPrimary(
                DuplicateEntityType::PERSON,
                (int) $person->id,
                $duplicates->pluck('id')
            );

            return $duplicates->whereIn('id', $filteredIds->all())->values();
        } catch (Exception $e) {
            Log::error('Error in person duplicate detection: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Check if a person has potential duplicates.
     *
     * @param Person $person
     * @return bool
     */
    public function hasPotentialDuplicates($person): bool
    {
        try {
            return $this->findPotentialDuplicates($person)->isNotEmpty();
        } catch (Exception $e) {
            Log::error('Error checking person duplicates: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Direct computation of potential duplicates (fallback method).
     */
    public function findPotentialDuplicatesDirectly($person): Collection
    {
        $duplicates = collect();

        try {
            // Check for email duplicates
            $emailDuplicates = $this->findDuplicatesByJsonField($person, 'emails');
            $duplicates = $duplicates->merge($emailDuplicates);

            // Check for phone duplicates
            $phoneDuplicates = $this->findDuplicatesByJsonField($person, 'phones');
            $duplicates = $duplicates->merge($phoneDuplicates);

            // Check for name duplicates
            $nameDuplicates = $this->findDuplicatesByName($person);
            $duplicates = $duplicates->merge($nameDuplicates);
        } catch (Exception $e) {
            Log::error('Error in person duplicate detection: ' . $e->getMessage());
        }

        // Remove duplicates from the collection and apply time/status filters
        return $duplicates->unique('id');
    }

    /**
     * Merge persons functionality.
     *
     * @param int $primaryPersonId
     * @param array $duplicatePersonIds
     * @param array $fieldMappings
     * @return Person
     */
    public function mergePersons($primaryPersonId, $duplicatePersonIds, $fieldMappings = [])
    {
        $primaryPerson = $this->findOrFail($primaryPersonId);
        $duplicatePersons = $this->findWhereIn('id', $duplicatePersonIds);

        try {
            DB::beginTransaction();

            // Apply field mappings to primary person
            foreach ($fieldMappings as $field => $sourcePersonId) {
                if ($sourcePersonId != $primaryPersonId) {
                    $sourcePerson = $duplicatePersons->firstWhere('id', $sourcePersonId);
                    if ($sourcePerson && !empty($sourcePerson->$field)) {
                        $primaryPerson->$field = $sourcePerson->$field;
                    }
                }
            }

            // Handle address merging
            if (isset($fieldMappings['address'])) {
                $addressSourcePersonId = $fieldMappings['address'];
                if ($addressSourcePersonId != $primaryPersonId) {
                    $this->mergeAddress($primaryPerson, $duplicatePersons->firstWhere('id', $addressSourcePersonId));
                }
            }

            // Save the updated primary person
            $primaryPerson->save();

            // Transfer activities and emails from duplicate persons to primary person
            foreach ($duplicatePersons as $duplicatePerson) {
                try {
                    // Add system activity for removed duplicate person
                    $this->addSystemActivity($primaryPerson, $duplicatePerson);
                } catch (Exception $e) {
                    Log::warning('Error adding system activity for duplicate removal: ' . $e->getMessage());
                }

                try {
                    // Transfer emails if they exist
                    if (method_exists($duplicatePerson, 'emails')) {
                        $duplicatePerson->emails()->update(['person_id' => $primaryPersonId]);
                    }

                    // Transfer activities
                    $duplicatePerson->activities()->sync([]);
                    $primaryPerson->activities()->attach($duplicatePerson->activities->pluck('id'));

                    // Add merge note to primary person's activities
                    $this->addMergeNote($primaryPerson, $duplicatePerson);
                } catch (Exception $e) {
                    Log::warning('Error transferring data from duplicate person: ' . $e->getMessage());
                }

                // Archive the duplicate person (soft delete or mark as archived)
                $duplicatePerson->delete();
            }

            DB::commit();

            // Clear cache for merged persons
            try {
                $cacheService = app(\App\Services\PersonDuplicateCacheService::class);
                $cacheService->handlePersonMerge($primaryPersonId, $duplicatePersonIds);
            } catch (Exception $e) {
                Log::warning('Error clearing person duplicate cache: ' . $e->getMessage());
            }

            return $primaryPerson;
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Error merging persons: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Merge address from duplicate person to primary person.
     */
    private function mergeAddress($primaryPerson, $duplicatePerson): void
    {
        if (!$duplicatePerson || !$duplicatePerson->address) {
            return;
        }

        $duplicateAddress = $duplicatePerson->address;

        if ($primaryPerson->address) {
            // Update existing address
            $primaryPerson->address->update($duplicateAddress->toArray());
        } else {
            // Create new address
            $addressData = $duplicateAddress->toArray();
            $addressData['person_id'] = $primaryPerson->id;
            unset($addressData['id'], $addressData['created_at'], $addressData['updated_at']);
            Address::create($addressData);
        }

        // Delete the duplicate's address
        $duplicateAddress->delete();
    }

    /**
     * Add system activity for person merge.
     */
    private function addSystemActivity($primaryPerson, $duplicatePerson): void
    {
        try {
            $activity = app(ActivityRepository::class)->create([
                'type' => 'note',
                'title' => 'Person Merge',
                'comment' => "Removed duplicate person \"{$duplicatePerson->name}\" (ID: {$duplicatePerson->id}) during merge operation.",
                'is_done' => true,
                'user_id' => auth()->id() ?: 1,
            ]);

            // Link activity to primary person
            $primaryPerson->activities()->attach($activity->id);

            Log::info('System activity created for person duplicate removal', [
                'primary_person_id' => $primaryPerson->id,
                'primary_person_name' => $primaryPerson->name,
                'removed_duplicate_id' => $duplicatePerson->id,
                'removed_duplicate_name' => $duplicatePerson->name,
                'activity_id' => $activity->id,
            ]);
        } catch (Exception $e) {
            Log::error('Error creating system activity for person merge: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Add merge note to primary person's activities.
     *
     * @param Person $primaryPerson
     * @param Person $duplicatePerson
     */
    private function addMergeNote($primaryPerson, $duplicatePerson): void
    {
        try {
            $activity = app(ActivityRepository::class)->create([
                'type' => 'note',
                'title' => 'Person Merged',
                'comment' => "Person #{$duplicatePerson->id} ({$duplicatePerson->name}) was merged into this person.",
                'is_done' => true,
                'user_id' => auth()->id() ?: 1,
            ]);

            // Link activity to primary person
            $primaryPerson->activities()->attach($activity->id);
        } catch (Exception $e) {
            Log::error('Error adding merge note: ' . $e->getMessage());
        }
    }
}
