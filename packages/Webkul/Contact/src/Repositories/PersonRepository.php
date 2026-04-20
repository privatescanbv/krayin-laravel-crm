<?php

namespace Webkul\Contact\Repositories;

use App\Enums\DuplicateEntityType;
use App\Models\Anamnesis;
use App\Models\PatientMessage;
use App\Models\SalesLead;
use App\Repositories\AddressRepository;
use App\Services\Concerns\JsonDuplicateMatcher;
use App\Services\DuplicateFalsePositiveService;
use App\Services\PersonDuplicateCacheService;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Contact\Contracts\Person;
use Webkul\Core\Eloquent\Repository;
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Lead;

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
        if (isset($data['address']) && ! empty($data['address'])) {
            app(AddressRepository::class)->upsertForEntity($person, $data['address']);
        }

        return $person;
    }

    /**
     * Update.
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
        if (isset($data['address']) && ! empty($data['address'])) {
            app(AddressRepository::class)->upsertForEntity($person, $data['address']);
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

    public function resolveEmailVariablesById($personId): array
    {
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

        // Normalize portal activation flag (checkbox/switch can submit as on/1/true)
        // Default should be active for newly created persons unless explicitly set.
        $data['is_active'] = filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);

        // Drop empty password submissions to avoid overwriting existing hashes
        if (array_key_exists('password', $data) && empty($data['password'])) {
            unset($data['password']);
        }

        if (isset($data['phones'])) {
            $data['phones'] = collect($data['phones'])->filter(fn ($number) => ! is_null($number['value']))->toArray();
        }

        return $data;
    }

    /**
     * Find potential duplicate persons based on email, phone, and name similarity.
     *
     * @param  Person  $person
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
            Log::error('Error in person duplicate detection: '.$e->getMessage());

            return collect();
        }
    }

    /**
     * Check if a person has potential duplicates.
     *
     * @param  Person  $person
     */
    public function hasPotentialDuplicates($person): bool
    {
        try {
            return $this->findPotentialDuplicates($person)->isNotEmpty();
        } catch (Exception $e) {
            Log::error('Error checking person duplicates: '.$e->getMessage());

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
            Log::error('Error in person duplicate detection: '.$e->getMessage());
        }

        // Remove duplicates from the collection and apply time/status filters
        return $duplicates->unique('id');
    }

    /**
     * Merge persons functionality.
     *
     * @param  int  $primaryPersonId
     * @param  array  $duplicatePersonIds
     * @param  array  $fieldMappings
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
                    if ($sourcePerson && ! empty($sourcePerson->$field)) {
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
                    $this->addSystemActivity($primaryPerson, $duplicatePerson);
                } catch (Exception $e) {
                    Log::warning('Error adding system activity for duplicate removal: '.$e->getMessage());
                }

                Email::where('person_id', $duplicatePerson->id)
                    ->update(['person_id' => $primaryPersonId]);

                // Transfer activities: re-point person_id FK activities to primary person
                Activity::where('person_id', $duplicatePerson->id)
                    ->update(['person_id' => $primaryPersonId]);

                // Transfer lead_persons pivot: attach duplicate's leads to primary, avoid duplicates
                $existingLeadIds = DB::table('lead_persons')
                    ->where('person_id', $primaryPersonId)
                    ->pluck('lead_id')
                    ->all();

                DB::table('lead_persons')
                    ->where('person_id', $duplicatePerson->id)
                    ->whereNotIn('lead_id', $existingLeadIds)
                    ->update(['person_id' => $primaryPersonId]);

                DB::table('lead_persons')
                    ->where('person_id', $duplicatePerson->id)
                    ->delete();

                // Transfer saleslead_persons pivot
                $existingSalesLeadIds = DB::table('saleslead_persons')
                    ->where('person_id', $primaryPersonId)
                    ->pluck('saleslead_id')
                    ->all();

                DB::table('saleslead_persons')
                    ->where('person_id', $duplicatePerson->id)
                    ->whereNotIn('saleslead_id', $existingSalesLeadIds)
                    ->update(['person_id' => $primaryPersonId]);

                DB::table('saleslead_persons')
                    ->where('person_id', $duplicatePerson->id)
                    ->delete();

                // Transfer contact_person_id on leads
                Lead::where('contact_person_id', $duplicatePerson->id)
                    ->update(['contact_person_id' => $primaryPersonId]);

                // Transfer contact_person_id on salesleads
                SalesLead::where('contact_person_id', $duplicatePerson->id)
                    ->update(['contact_person_id' => $primaryPersonId]);

                $this->resolveAnamnesisConflictsBeforePersonReassign($primaryPersonId, (int) $duplicatePerson->id);

                Anamnesis::where('person_id', $duplicatePerson->id)
                    ->update(['person_id' => $primaryPersonId]);

                PatientMessage::where('person_id', $duplicatePerson->id)
                    ->update(['person_id' => $primaryPersonId]);

                $this->reassignPersonScopedPivotAndFks($primaryPersonId, (int) $duplicatePerson->id);

                try {
                    $this->addMergeNote($primaryPerson, $duplicatePerson);
                } catch (Exception $e) {
                    Log::warning('Error adding merge note after person merge: '.$e->getMessage());
                }

                $duplicatePerson->delete();
            }

            DB::commit();

            // Clear cache for merged persons
            try {
                $cacheService = app(PersonDuplicateCacheService::class);
                $cacheService->handlePersonMerge($primaryPersonId, $duplicatePersonIds);
            } catch (Exception $e) {
                Log::warning('Error clearing person duplicate cache: '.$e->getMessage());
            }

            return $primaryPerson;
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Error merging persons: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Before reassigning anamnesis rows to the primary person, drop duplicates that would violate
     * unique(lead_id, person_id) or unique(sales_id, person_id), keeping the newest row.
     */
    private function resolveAnamnesisConflictsBeforePersonReassign(int $primaryPersonId, int $duplicatePersonId): void
    {
        $leadConflictIds = DB::table('anamnesis as d')
            ->where('d.person_id', $duplicatePersonId)
            ->whereNotNull('d.lead_id')
            ->whereExists(function ($query) use ($primaryPersonId) {
                $query->selectRaw('1')
                    ->from('anamnesis as p')
                    ->whereColumn('p.lead_id', 'd.lead_id')
                    ->where('p.person_id', $primaryPersonId);
            })
            ->pluck('d.lead_id');

        foreach ($leadConflictIds->unique()->all() as $leadId) {
            $this->deleteOlderAnamnesisRowsForLead((int) $leadId, $primaryPersonId, $duplicatePersonId);
        }

        $salesConflictIds = DB::table('anamnesis as d')
            ->where('d.person_id', $duplicatePersonId)
            ->whereNotNull('d.sales_id')
            ->whereExists(function ($query) use ($primaryPersonId) {
                $query->selectRaw('1')
                    ->from('anamnesis as p')
                    ->whereColumn('p.sales_id', 'd.sales_id')
                    ->where('p.person_id', $primaryPersonId);
            })
            ->pluck('d.sales_id');

        foreach ($salesConflictIds->unique()->all() as $salesId) {
            $this->deleteOlderAnamnesisRowsForSales((int) $salesId, $primaryPersonId, $duplicatePersonId);
        }
    }

    private function deleteOlderAnamnesisRowsForLead(int $leadId, int $primaryPersonId, int $duplicatePersonId): void
    {
        $rows = DB::table('anamnesis')
            ->where('lead_id', $leadId)
            ->whereIn('person_id', [$primaryPersonId, $duplicatePersonId])
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        foreach ($rows->skip(1) as $row) {
            DB::table('anamnesis')->where('id', $row->id)->delete();
        }
    }

    private function deleteOlderAnamnesisRowsForSales(int $salesId, int $primaryPersonId, int $duplicatePersonId): void
    {
        $rows = DB::table('anamnesis')
            ->where('sales_id', $salesId)
            ->whereIn('person_id', [$primaryPersonId, $duplicatePersonId])
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        foreach ($rows->skip(1) as $row) {
            DB::table('anamnesis')->where('id', $row->id)->delete();
        }
    }

    /**
     * Reassign remaining person_id foreign keys so soft-deleted duplicates do not orphan related rows.
     */
    private function reassignPersonScopedPivotAndFks(int $primaryPersonId, int $duplicatePersonId): void
    {
        $existingTagIds = DB::table('person_tags')
            ->where('person_id', $primaryPersonId)
            ->pluck('tag_id')
            ->all();

        DB::table('person_tags')
            ->where('person_id', $duplicatePersonId)
            ->whereNotIn('tag_id', $existingTagIds)
            ->update(['person_id' => $primaryPersonId]);

        DB::table('person_tags')
            ->where('person_id', $duplicatePersonId)
            ->delete();

        $preferenceKeysOnPrimary = DB::table('person_preferences')
            ->where('person_id', $primaryPersonId)
            ->pluck('key')
            ->all();

        DB::table('person_preferences')
            ->where('person_id', $duplicatePersonId)
            ->whereIn('key', $preferenceKeysOnPrimary)
            ->delete();

        DB::table('person_preferences')
            ->where('person_id', $duplicatePersonId)
            ->update(['person_id' => $primaryPersonId]);

        $existingPortalActivityIds = DB::table('activity_portal_persons')
            ->where('person_id', $primaryPersonId)
            ->pluck('activity_id')
            ->all();

        DB::table('activity_portal_persons')
            ->where('person_id', $duplicatePersonId)
            ->whereNotIn('activity_id', $existingPortalActivityIds)
            ->update(['person_id' => $primaryPersonId]);

        DB::table('activity_portal_persons')
            ->where('person_id', $duplicatePersonId)
            ->delete();

        $primaryAddressId = DB::table('persons')->where('id', $primaryPersonId)->value('address_id');
        $duplicateAddressId = DB::table('persons')->where('id', $duplicatePersonId)->value('address_id');
        if (empty($primaryAddressId) && ! empty($duplicateAddressId)) {
            DB::table('persons')->where('id', $primaryPersonId)->update(['address_id' => $duplicateAddressId]);
        }

        $existingConfirmationOrderIds = DB::table('order_person_confirmations')
            ->where('person_id', $primaryPersonId)
            ->pluck('order_id')
            ->all();

        DB::table('order_person_confirmations')
            ->where('person_id', $duplicatePersonId)
            ->whereNotIn('order_id', $existingConfirmationOrderIds)
            ->update(['person_id' => $primaryPersonId]);

        DB::table('order_person_confirmations')
            ->where('person_id', $duplicatePersonId)
            ->delete();

        DB::table('order_items')
            ->where('person_id', $duplicatePersonId)
            ->update(['person_id' => $primaryPersonId]);

        DB::table('afb_person_documents')
            ->where('person_id', $duplicatePersonId)
            ->update(['person_id' => $primaryPersonId]);
    }

    /**
     * Merge address from duplicate person to primary person.
     */
    private function mergeAddress($primaryPerson, $duplicatePerson): void
    {
        if (! $duplicatePerson || ! $duplicatePerson->address) {
            return;
        }

        $duplicateAddress = $duplicatePerson->address;

        // Prepare address data from duplicate
        $addressData = [
            'street'              => $duplicateAddress->street,
            'house_number'        => $duplicateAddress->house_number,
            'house_number_suffix' => $duplicateAddress->house_number_suffix,
            'postal_code'         => $duplicateAddress->postal_code,
            'city'                => $duplicateAddress->city,
            'state'               => $duplicateAddress->state,
            'country'             => $duplicateAddress->country,
        ];

        // Use the AddressRepository to upsert the address
        app(AddressRepository::class)->upsertForEntity($primaryPerson, $addressData);
    }

    /**
     * Add system activity for person merge.
     */
    private function addSystemActivity($primaryPerson, $duplicatePerson): void
    {
        try {
            $activity = app(ActivityRepository::class)->create([
                'type'      => 'note',
                'title'     => 'Person Merge',
                'comment'   => "Removed duplicate person \"{$duplicatePerson->name}\" (ID: {$duplicatePerson->id}) during merge operation.",
                'is_done'   => true,
                'person_id' => $primaryPerson->id,
                'user_id'   => auth()->id() ?: 1,
            ]);

            Log::info('System activity created for person duplicate removal', [
                'primary_person_id'      => $primaryPerson->id,
                'primary_person_name'    => $primaryPerson->name,
                'removed_duplicate_id'   => $duplicatePerson->id,
                'removed_duplicate_name' => $duplicatePerson->name,
                'activity_id'            => $activity->id,
            ]);
        } catch (Exception $e) {
            Log::error('Error creating system activity for person merge: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Add merge note to primary person's activities.
     *
     * @param  Person  $primaryPerson
     * @param  Person  $duplicatePerson
     */
    private function addMergeNote($primaryPerson, $duplicatePerson): void
    {
        try {
            app(ActivityRepository::class)->create([
                'type'      => 'note',
                'title'     => 'Person Merged',
                'comment'   => "Person #{$duplicatePerson->id} ({$duplicatePerson->name}) was merged into this person.",
                'is_done'   => true,
                'person_id' => $primaryPerson->id,
                'user_id'   => auth()->id() ?: 1,
            ]);
        } catch (Exception $e) {
            Log::error('Error adding merge note: '.$e->getMessage());
        }
    }
}
