<?php

namespace Webkul\Contact\Repositories;

use App\Enums\DuplicateEntityType;
use App\Exceptions\CannotMergePersonWithPortalException;
use App\Repositories\AddressRepository;
use App\Services\Concerns\JsonDuplicateMatcher;
use App\Services\DuplicateFalsePositiveService;
use App\Services\PersonDuplicateCacheService;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Contact\Contracts\Person;
use Webkul\Contact\Models\Person as PersonModel;
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
     * Resolved lazily: PersonDuplicateCacheService depends on this repository.
     */
    protected function getCacheService(): PersonDuplicateCacheService
    {
        return app(PersonDuplicateCacheService::class);
    }

    /**
     * Create.
     */
    public function create(array $attributes): Person
    {
        $attributes = $this->sanitizeRequestedPersonData($attributes);

        if (! empty($attributes['organization_name'])) {
            $organization = $this->fetchOrCreateOrganizationByName($attributes['organization_name']);

            $attributes['organization_id'] = $organization->id;
        }

        if (isset($attributes['user_id'])) {
            $attributes['user_id'] = $attributes['user_id'] ?: null;
        }

        $person = parent::create($attributes);

        $this->attributeValueRepository->save(array_merge($attributes, [
            'entity_id' => $person->id,
        ]));

        // Handle address data for new persons
        if (isset($attributes['address']) && ! empty($attributes['address'])) {
            app(AddressRepository::class)->upsertForEntity($person, $attributes['address']);
        }

        return $person;
    }

    /**
     * Update.
     */
    public function update(array $attributes, $id, $attributeCodes = []): Person
    {
        $attributes = $this->sanitizeRequestedPersonData($attributes);

        $attributes['user_id'] = empty($attributes['user_id']) ? null : $attributes['user_id'];

        if (! empty($attributes['organization_name'])) {
            $organization = $this->fetchOrCreateOrganizationByName($attributes['organization_name']);

            $attributes['organization_id'] = $organization->id;

            unset($attributes['organization_name']);
        }

        $person = parent::update($attributes, $id);

        /**
         * If specific attribute codes are provided then only save those and return.
         */
        if (! empty($attributeCodes)) {
            $conditions = ['entity_type' => $attributes['entity_type']];

            if (isset($attributes['quick_add'])) {
                $conditions['quick_add'] = 1;
            }

            $attributeModels = $this->attributeRepository->where($conditions)
                ->whereIn('code', $attributeCodes)
                ->get();

            $this->attributeValueRepository->save(array_merge($attributes, [
                'entity_id' => $person->id,
            ]), $attributeModels);

            return $person;
        }

        $this->attributeValueRepository->save(array_merge($attributes, [
            'entity_id' => $person->id,
        ]));

        // Handle address data
        if (isset($attributes['address']) && ! empty($attributes['address'])) {
            app(AddressRepository::class)->upsertForEntity($person, $attributes['address']);
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

        // A person can never be its own duplicate; merging it into itself would soft delete it.
        $duplicatePersonIds = array_values(array_diff($duplicatePersonIds, [$primaryPersonId]));

        if (empty($duplicatePersonIds)) {
            return $primaryPerson;
        }

        $duplicatePersons = $this->findWhereIn('id', $duplicatePersonIds);

        $this->guardAgainstMergingPortalDuplicates($duplicatePersons);

        // Capture now, while the duplicates still exist: every third person that matches one of the
        // merged-away persons needs its has_duplicates flag recomputed afterwards, otherwise it stays
        // stale (true, but no real match) until the hourly index rebuild.
        $mergeIds = array_map('intval', [$primaryPersonId, ...$duplicatePersonIds]);
        $counterpartIds = $duplicatePersons
            ->flatMap(fn ($dup) => $this->getCacheService()->counterpartIdsFor($dup)) // Collection<int, int>
            ->reject(fn (int $id) => in_array($id, $mergeIds, true))
            ->unique()
            ->values()
            ->all();

        try {
            DB::beginTransaction();

            if (! empty($fieldMappings)) {
                $updateData = [];
                $addressSourcePersonId = null;

                foreach ($fieldMappings as $field => $sourcePersonId) {
                    if ($sourcePersonId == $primaryPersonId) {
                        continue;
                    }

                    $sourcePerson = $duplicatePersons->firstWhere('id', $sourcePersonId);

                    if ($field === 'address') {
                        // Address is a relation, not a column — never assign it onto the model.
                        $addressSourcePersonId = $sourcePersonId;
                    } elseif ($field === 'is_active' && $sourcePerson) {
                        // Boolean: !empty() would skip a deliberate "inactive" choice.
                        $updateData['is_active'] = (bool) $sourcePerson->is_active;
                    } elseif ($sourcePerson && ! empty($sourcePerson->$field)) {
                        $updateData[$field] = in_array($field, ['emails', 'phones'], true)
                            ? $this->unionContactValues($updateData[$field] ?? $primaryPerson->$field, $sourcePerson->$field)
                            : $sourcePerson->$field;
                    }
                }

                if (! empty($updateData)) {
                    $primaryPerson->update($updateData);
                }

                if ($addressSourcePersonId) {
                    $this->mergeAddress($primaryPerson, $duplicatePersons->firstWhere('id', $addressSourcePersonId));
                }
            }

            foreach ($duplicatePersons as $duplicatePerson) {
                // Audit first and without try/catch: these notes are the only trail linking a
                // duplicate to the person it was merged into (see persons:report-merge-orphans).
                $this->addSystemActivity($primaryPerson, $duplicatePerson);
                $this->addMergeNote($primaryPerson, $duplicatePerson);

                $this->transferPersonRelations((int) $primaryPerson->id, (int) $duplicatePerson->id);
                $this->adoptKeycloakAccount($primaryPerson, $duplicatePerson);

                // Soft-delete; keycloak_user_id was cleared so PersonObserver will not HTTP-delete.
                $duplicatePerson->delete();
            }

            DB::commit();

            try {
                $this->getCacheService()->handlePersonMerge($primaryPersonId, $duplicatePersonIds, $counterpartIds);
            } catch (Exception $e) {
                Log::warning('Error clearing person duplicate cache: '.$e->getMessage());
            }

            return $primaryPerson->fresh();
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Error merging persons: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Merge two [{label, value, is_default}] lists (emails/phones) instead of letting one replace the
     * other. Deduplicated on value; only the primary keeps its default flag.
     *
     * @param  array<int, array<string, mixed>>|null  $primary
     * @param  array<int, array<string, mixed>>|null  $source
     * @return array<int, array<string, mixed>>
     */
    private function unionContactValues(?array $primary, ?array $source): array
    {
        $merged = $primary ?? [];
        $existing = array_column($merged, 'value');

        foreach ($source ?? [] as $entry) {
            if (empty($entry['value']) || in_array($entry['value'], $existing, true)) {
                continue;
            }

            $entry['is_default'] = false;
            $merged[] = $entry;
            $existing[] = $entry['value'];
        }

        return $merged;
    }

    /**
     * Re-point everything that hangs off the duplicate person to the primary person.
     *
     * The duplicate is only soft deleted, so none of the ON DELETE constraints fire and every related
     * row would silently stay behind on an invisible person. Uses query builder throughout so soft
     * deletes are ignored.
     */
    public function transferPersonRelations(int $primaryPersonId, int $duplicatePersonId): void
    {
        foreach (['emails', 'anamnesis', 'patient_messages', 'order_items', 'afb_person_documents'] as $table) {
            // Anamnesis needs conflict resolution first — handled below.
            if ($table === 'anamnesis') {
                continue;
            }

            DB::table($table)->where('person_id', $duplicatePersonId)->update(['person_id' => $primaryPersonId]);
        }

        $this->transferActivitiesSkippingDuplicates('person_id', $primaryPersonId, $duplicatePersonId);

        DB::table('patient_notifications')
            ->where('patient_id', $duplicatePersonId)
            ->update(['patient_id' => $primaryPersonId]);

        DB::table('leads')
            ->where('contact_person_id', $duplicatePersonId)
            ->update(['contact_person_id' => $primaryPersonId]);

        DB::table('salesleads')
            ->where('contact_person_id', $duplicatePersonId)
            ->update(['contact_person_id' => $primaryPersonId]);

        $this->resolveAnamnesisConflictsBeforePersonReassign($primaryPersonId, $duplicatePersonId);

        DB::table('anamnesis')
            ->where('person_id', $duplicatePersonId)
            ->update(['person_id' => $primaryPersonId]);

        $this->movePersonPivotRows('lead_persons', 'lead_id', $primaryPersonId, $duplicatePersonId);
        $this->movePersonPivotRows('saleslead_persons', 'saleslead_id', $primaryPersonId, $duplicatePersonId);
        $this->movePersonPivotRows('person_tags', 'tag_id', $primaryPersonId, $duplicatePersonId);
        $this->movePersonPivotRows('activity_portal_persons', 'activity_id', $primaryPersonId, $duplicatePersonId);
        $this->movePersonPivotRows('order_person_confirmations', 'order_id', $primaryPersonId, $duplicatePersonId);

        $this->transferInkoopPersonLinks($primaryPersonId, $duplicatePersonId);

        // person_preferences: unique-ish on (person_id, key); primary wins on colliding keys.
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

        // Custom attribute values: unique (entity_type, entity_id, attribute_id), primary wins.
        $primaryAttributeIds = DB::table('attribute_values')
            ->where('entity_type', 'persons')
            ->where('entity_id', $primaryPersonId)
            ->pluck('attribute_id')
            ->all();

        DB::table('attribute_values')
            ->where('entity_type', 'persons')
            ->where('entity_id', $duplicatePersonId)
            ->whereIn('attribute_id', $primaryAttributeIds)
            ->delete();

        DB::table('attribute_values')
            ->where('entity_type', 'persons')
            ->where('entity_id', $duplicatePersonId)
            ->update(['entity_id' => $primaryPersonId]);

        // "Not a duplicate" pairs are meaningless once one side is gone.
        DB::table('duplicates_false_positives')
            ->where('entity_type', DuplicateEntityType::PERSON->value)
            ->where(function ($query) use ($duplicatePersonId) {
                $query->where('entity_id_1', $duplicatePersonId)
                    ->orWhere('entity_id_2', $duplicatePersonId);
            })
            ->delete();

        // Adopt the address when the primary has none and no explicit field mapping was made.
        $primaryAddressId = DB::table('persons')->where('id', $primaryPersonId)->value('address_id');
        $duplicateAddressId = DB::table('persons')->where('id', $duplicatePersonId)->value('address_id');

        if (empty($primaryAddressId) && ! empty($duplicateAddressId)) {
            DB::table('persons')->where('id', $primaryPersonId)->update(['address_id' => $duplicateAddressId]);
        }
    }

    /**
     * Move activities to the primary person, skipping any duplicate activity that already matches
     * one the primary has (same title + status) so a merge never doubles up an activity. Skipped
     * rows stay on the (soft-deleted) duplicate - no data lost, just not surfaced twice.
     */
    private function transferActivitiesSkippingDuplicates(string $foreignKey, int $primaryId, int $duplicateId): void
    {
        $existing = DB::table('activities')
            ->where($foreignKey, $primaryId)
            ->get(['title', 'status'])
            ->map(fn ($row) => $row->title.'|'.$row->status)
            ->all();

        $idsToTransfer = DB::table('activities')
            ->where($foreignKey, $duplicateId)
            ->get(['id', 'title', 'status'])
            ->reject(fn ($row) => in_array($row->title.'|'.$row->status, $existing, true))
            ->pluck('id');

        if ($idsToTransfer->isNotEmpty()) {
            DB::table('activities')->whereIn('id', $idsToTransfer)->update([$foreignKey => $primaryId]);
        }
    }

    /**
     * Re-point inkoop invoice patients that were matched to the duplicate. crm_id is a string
     * copy of persons.id. Skip invoices the primary is already linked on so two inkoop rows on
     * the same invoice never both claim the same CRM person.
     */
    private function transferInkoopPersonLinks(int $primaryPersonId, int $duplicatePersonId): void
    {
        $primaryCrmId = (string) $primaryPersonId;
        $duplicateCrmId = (string) $duplicatePersonId;

        $invoiceIdsOnPrimary = DB::table('inkoop_persons')
            ->where('crm_id', $primaryCrmId)
            ->pluck('invoice_id')
            ->all();

        $query = DB::table('inkoop_persons')->where('crm_id', $duplicateCrmId);

        if ($invoiceIdsOnPrimary !== []) {
            $query->whereNotIn('invoice_id', $invoiceIdsOnPrimary);
        }

        $query->update(['crm_id' => $primaryCrmId]);
    }

    /**
     * Move pivot rows to the primary person, dropping ones it already has so unique indexes never collide.
     */
    private function movePersonPivotRows(string $table, string $otherKey, int $primaryPersonId, int $duplicatePersonId): void
    {
        $existing = DB::table($table)->where('person_id', $primaryPersonId)->pluck($otherKey)->all();

        DB::table($table)
            ->where('person_id', $duplicatePersonId)
            ->whereNotIn($otherKey, $existing)
            ->update(['person_id' => $primaryPersonId]);

        DB::table($table)->where('person_id', $duplicatePersonId)->delete();
    }

    /**
     * A person with a patient portal account must never be the archived side of a merge.
     * Staff must first make that person primary, or revoke the portal account by hand.
     *
     * @param  Collection<int, mixed>  $duplicatePersons
     *
     * @throws CannotMergePersonWithPortalException
     */
    private function guardAgainstMergingPortalDuplicates(Collection $duplicatePersons): void
    {
        $blockedIds = $duplicatePersons
            ->filter(fn ($person): bool => ! empty($person->keycloak_user_id))
            ->pluck('id')
            ->all();

        if ($blockedIds !== []) {
            throw CannotMergePersonWithPortalException::forPersonIds($blockedIds);
        }
    }

    /**
     * If the primary has no portal account and the duplicate does, adopt it. Always clear the
     * duplicate's keycloak_user_id before soft-delete so PersonObserver::deleted does not fire a
     * real HTTP delete against Keycloak (that cannot be rolled back with the DB transaction).
     *
     * The in-memory model must be cleared too: the observer reads $person->keycloak_user_id, not the DB.
     *
     * Portal duplicates are rejected before this runs; the adopt branch is a last-resort safety net.
     */
    private function adoptKeycloakAccount(PersonModel $primaryPerson, PersonModel $duplicatePerson): void
    {
        $primaryKeycloakId = $primaryPerson->keycloak_user_id
            ?: DB::table('persons')->where('id', $primaryPerson->id)->value('keycloak_user_id');
        $duplicateKeycloakId = $duplicatePerson->keycloak_user_id
            ?: DB::table('persons')->where('id', $duplicatePerson->id)->value('keycloak_user_id');

        if (empty($primaryKeycloakId) && ! empty($duplicateKeycloakId)) {
            DB::table('persons')->where('id', $primaryPerson->id)->update([
                'keycloak_user_id' => $duplicateKeycloakId,
            ]);
            $primaryPerson->keycloak_user_id = $duplicateKeycloakId;
        }

        if (! empty($duplicateKeycloakId)) {
            DB::table('persons')->where('id', $duplicatePerson->id)->update([
                'keycloak_user_id' => null,
            ]);
            $duplicatePerson->keycloak_user_id = null;
        }
    }

    /**
     * Before reassigning anamnesis rows to the primary person, drop duplicates that would violate
     * unique(lead_id, person_id), unique(sales_id, person_id) or unique(order_id, person_id),
     * keeping the newest row.
     */
    private function resolveAnamnesisConflictsBeforePersonReassign(int $primaryPersonId, int $duplicatePersonId): void
    {
        $this->resolveAnamnesisConflictsForScope('lead_id', $primaryPersonId, $duplicatePersonId);
        $this->resolveAnamnesisConflictsForScope('sales_id', $primaryPersonId, $duplicatePersonId);
        $this->resolveAnamnesisConflictsForScope('order_id', $primaryPersonId, $duplicatePersonId);
    }

    private function resolveAnamnesisConflictsForScope(string $scopeColumn, int $primaryPersonId, int $duplicatePersonId): void
    {
        $conflictIds = DB::table('anamnesis as d')
            ->where('d.person_id', $duplicatePersonId)
            ->whereNotNull("d.{$scopeColumn}")
            ->whereExists(function ($query) use ($primaryPersonId, $scopeColumn) {
                $query->selectRaw('1')
                    ->from('anamnesis as p')
                    ->whereColumn("p.{$scopeColumn}", "d.{$scopeColumn}")
                    ->where('p.person_id', $primaryPersonId);
            })
            ->pluck("d.{$scopeColumn}");

        foreach ($conflictIds->unique()->all() as $scopeId) {
            $rows = DB::table('anamnesis')
                ->where($scopeColumn, $scopeId)
                ->whereIn('person_id', [$primaryPersonId, $duplicatePersonId])
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();

            foreach ($rows->skip(1) as $row) {
                DB::table('anamnesis')->where('id', $row->id)->delete();
            }
        }
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
     *
     * Titles/comments are the trail used by persons:report-merge-orphans. Keep the parseable
     * "(ID: n)" / "Person #n " markers stable. Also matches the legacy "Person Merge" title so
     * historical merges remain reportable.
     */
    private function addSystemActivity($primaryPerson, $duplicatePerson): void
    {
        $activity = app(ActivityRepository::class)->create([
            'type'      => 'system',
            'title'     => 'System: Duplicate Person Removed',
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
    }

    /**
     * Add merge note to primary person's activities.
     */
    private function addMergeNote(PersonModel $primaryPerson, PersonModel $duplicatePerson): void
    {
        app(ActivityRepository::class)->create([
            'type'      => 'note',
            'title'     => 'Person Merged',
            'comment'   => "Person #{$duplicatePerson->id} ({$duplicatePerson->name}) was merged into this person.",
            'is_done'   => true,
            'person_id' => $primaryPerson->id,
            'user_id'   => auth()->id() ?: 1,
        ]);
    }
}
