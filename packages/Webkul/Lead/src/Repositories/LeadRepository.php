<?php

namespace Webkul\Lead\Repositories;

use App\Models\Anamnesis;
use Carbon\Carbon;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Address;
use InvalidArgumentException;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Core\Eloquent\Repository;
use Webkul\Lead\Contracts\Lead;
use Webkul\Lead\Models\Lead as LeadModel;

class LeadRepository extends Repository
{
    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'title',
        'lead_value',
        'status',
        'user_id',
        'user.name',
        'persons.name',
        'lead_source_id',
        'lead_type_id',
        'lead_pipeline_id',
        'lead_pipeline_stage_id',
        'created_at',
        'closed_at',
        'expected_close_date',
    ];

    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        protected StageRepository          $stageRepository,
        protected PersonRepository         $personRepository,
        protected ProductRepository        $productRepository,
        protected AttributeRepository      $attributeRepository,
        protected AttributeValueRepository $attributeValueRepository,
        Container                          $container
    )
    {
        parent::__construct($container);
    }

    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return Lead::class;
    }

    /**
     * Get leads query.
     *
     * @param int $pipelineId
     * @param int $pipelineStageId
     * @param string $term
     * @param string $createdAtRange
     * @return mixed
     */
    public function getLeadsQuery($pipelineId, $pipelineStageId, $term, $createdAtRange)
    {
        return $this->with([
            'attribute_values',
            'pipeline',
            'stage',
        ])->scopeQuery(function ($query) use ($pipelineId, $pipelineStageId, $term, $createdAtRange) {
            return $query->select(
                'leads.id as id',
                'leads.created_at as created_at',
                'title',
                'lead_value',
                'lead_pipelines.id as lead_pipeline_id',
                'lead_pipeline_stages.name as status',
                'lead_pipeline_stages.id as lead_pipeline_stage_id'
            )
                ->addSelect(DB::raw('DATEDIFF(' . DB::getTablePrefix() . 'leads.created_at + INTERVAL lead_pipelines.rotten_days DAY, now()) as rotten_days'))
                ->leftJoin('lead_pipelines', 'leads.lead_pipeline_id', '=', 'lead_pipelines.id')
                ->leftJoin('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
                ->where('title', 'like', "%$term%")
                ->where('leads.lead_pipeline_id', $pipelineId)
                ->where('leads.lead_pipeline_stage_id', $pipelineStageId)
                ->when($createdAtRange, function ($query) use ($createdAtRange) {
                    return $query->whereBetween('leads.created_at', $createdAtRange);
                })
                ->where(function ($query) {
                    if ($userIds = bouncer()->getAuthorizedUserIds()) {
                        $query->whereIn('leads.user_id', $userIds);
                    }
                });
        });
    }

    /**
     * Create.
     *
     * @return Lead
     */
    public function create(array $data): Lead
    {


        // Handle multiple persons
        $personsToAttach = [];

        /**
         * If persons array is provided, process each person
         */
        if (isset($data['persons']) && is_array($data['persons'])) {
            foreach ($data['persons'] as $personData) {
                if (!empty($personData) && $this->hasValidPersonData($personData)) {
                    if (!empty($personData['id'])) {
                        $person = $this->personRepository->findOrFail($personData['id']);
                    } else {
                        $person = $this->personRepository->create(array_merge($personData, [
                            'entity_type' => 'persons',
                        ]));
                    }
                    $personsToAttach[] = $person->id;
                }
            }
        }

        /**
         * If person_ids array is provided directly
         */
        if (isset($data['person_ids']) && is_array($data['person_ids'])) {
            $personsToAttach = array_merge($personsToAttach, array_filter($data['person_ids']));
        }

        if (empty($data['expected_close_date'])) {
            $data['expected_close_date'] = null;
        }

        // Handle empty organization_id
        if (empty($data['organization_id']) || !is_numeric($data['organization_id'])) {
            $data['organization_id'] = null;
        }

        $lead = parent::create(array_merge([
            'lead_pipeline_id' => 1,
            'lead_pipeline_stage_id' => 1,
        ], $data));

        $this->attributeValueRepository->save(array_merge($data, [
            'entity_id' => $lead->id,
        ]));

        if (isset($data['products'])) {
            foreach ($data['products'] as $product) {
                $this->productRepository->create(array_merge($product, [
                    'lead_id' => $lead->id,
                    'amount' => $product['price'] * $product['quantity'],
                ]));
            }
        }

        // Handle address data for new leads
        if (isset($data['address']) && !empty($data['address'])) {
            $addressData = $data['address'];
            $hasAddressData = !empty(array_filter($addressData));

            if ($hasAddressData) {
                Address::create(array_merge($addressData, [
                    'lead_id' => $lead->id,
                ]));
            }
        }

                // Attach persons to the lead
        if (!empty($personsToAttach)) {
            $lead->attachPersons(array_unique($personsToAttach));

            // Create anamnesis when first person is attached
            $this->createAnamnesisForLead($lead);
        }

        return $lead;
    }

    /**
     * Update.
     *
     * @param int $id
     * @param array|\Illuminate\Database\Eloquent\Collection $attributes
     * @return Lead
     */
    public function update(array $data, $id, $attributes = []): Lead
    {
        // Debug: Log what data is received
        Log::info('LeadRepository update received data', [
            'lead_id' => $id,
            'has_persons' => array_key_exists('persons', $data),
            'has_person_ids' => array_key_exists('person_ids', $data),
            'persons_data' => $data['persons'] ?? null,
            'person_ids_data' => $data['person_ids'] ?? null,
        ]);

        // Handle multiple persons update
        $personsToSync = [];

        /**
         * If persons array is provided, process each person
         */
        if (isset($data['persons']) && is_array($data['persons'])) {
            foreach ($data['persons'] as $personData) {
                if (!empty($personData) && $this->hasValidPersonData($personData)) {
                    if (!empty($personData['id'])) {
                        $person = $this->personRepository->findOrFail($personData['id']);
                    } else {
                        $person = $this->personRepository->create(array_merge($personData, [
                            'entity_type' => 'persons',
                        ]));
                    }
                    $personsToSync[] = $person->id;
                }
            }
        }

        /**
         * If person_ids array is provided directly
         */
        if (isset($data['person_ids']) && is_array($data['person_ids'])) {
            $personsToSync = array_merge($personsToSync, array_filter($data['person_ids']));
        }

        if (isset($data['lead_pipeline_stage_id'])) {
            $stage = $this->stageRepository->find($data['lead_pipeline_stage_id']);

            if (in_array($stage->code, ['won', 'lost'])) {
                $data['closed_at'] = $data['closed_at'] ?? Carbon::now();
            } else {
                $data['closed_at'] = null;
            }
        }

        if (empty($data['expected_close_date'])) {
            $data['expected_close_date'] = null;
        }

        // Handle empty organization_id
        if (empty($data['organization_id']) || !is_numeric($data['organization_id'])) {
            $data['organization_id'] = null;
        }

        // Handle address data
        if (isset($data['address']) && !empty($data['address'])) {
            $addressData = $data['address'];
            $hasAddressData = !empty(array_filter($addressData));

            if ($hasAddressData) {
                // Check if lead already has an address
                $existingAddress = Address::where('lead_id', $id)->first();

                if ($existingAddress) {
                    // Update existing address
                    $existingAddress->update($addressData);
                } else {
                    // Create new address
                    Address::create(array_merge($addressData, [
                        'lead_id' => $id,
                    ]));
                }
            }
        }

        $lead = parent::update($data, $id);

        /**
         * If attributes are provided, only save the provided attributes and return.
         * A collection of attributes may also be provided, which will be treated as valid,
         * regardless of whether it is empty or not.
         */
        if (!empty($attributes)) {
            /**
             * If attributes are provided as an array, then fetch the attributes from the database;
             * otherwise, use the provided collection of attributes.
             */
            if (is_array($attributes)) {
                $conditions = ['entity_type' => $data['entity_type']];

                if (isset($data['quick_add'])) {
                    $conditions['quick_add'] = 1;
                }

                $attributes = $this->attributeRepository->where($conditions)
                    ->whereIn('code', $attributes)
                    ->get();
            }

            $this->attributeValueRepository->save(array_merge($data, [
                'entity_id' => $lead->id,
            ]), $attributes);

            return $lead;
        }

        $this->attributeValueRepository->save(array_merge($data, [
            'entity_id' => $lead->id,
        ]));

        $previousProductIds = $lead->products()->pluck('id');

        if (isset($data['products'])) {
            foreach ($data['products'] as $productId => $productInputs) {
                if (Str::contains($productId, 'product_')) {
                    $this->productRepository->create(array_merge([
                        'lead_id' => $lead->id,
                    ], $productInputs));
                } else {
                    if (is_numeric($index = $previousProductIds->search($productId))) {
                        $previousProductIds->forget($index);
                    }

                    $this->productRepository->update($productInputs, $productId);
                }
            }
        }

        foreach ($previousProductIds as $productId) {
            $this->productRepository->delete($productId);
        }

                // Sync persons to the lead
        // Only sync if persons data was explicitly provided (not for partial updates like stage changes)
        if (array_key_exists('persons', $data) || array_key_exists('person_ids', $data)) {
            // Get current person count before sync
            $hadPersons = $lead->persons->count() > 0;

            $lead->syncPersons(array_unique($personsToSync));

            // Manage anamnesis lifecycle based on person changes
            $hasPersonsNow = count($personsToSync) > 0;

            if (!$hadPersons && $hasPersonsNow) {
                // First person attached - create anamnesis
                $this->createAnamnesisForLead($lead);
            } elseif ($hadPersons && !$hasPersonsNow) {
                // All persons removed - delete anamnesis
                $this->deleteAnamnesisForLead($lead);
            }
        }

        return $lead;
    }

    /**
     * Find potential duplicate leads based on email, phone, and name similarity.
     */
    public function findPotentialDuplicates($lead): Collection
    {
        $duplicates = collect();

        try {
            // Check for email duplicates
            $emailDuplicates = $this->findEmailDuplicates($lead);
            $duplicates = $duplicates->merge($emailDuplicates);

            // Check for phone duplicates
            $phoneDuplicates = $this->findPhoneDuplicates($lead);
            $duplicates = $duplicates->merge($phoneDuplicates);

            // Check for name similarity
            $nameDuplicates = $this->findNameDuplicates($lead);
            $duplicates = $duplicates->merge($nameDuplicates);

        } catch (Exception $e) {
            Log::error('Error in duplicate detection: ' . $e->getMessage());
        }

        // Remove duplicates from the collection and return unique leads
        return $duplicates->unique('id');
    }

    /**
     * Check if a lead has potential duplicates.
     *
     */
    public function hasPotentialDuplicates(Lead $lead): bool
    {
        return $this->findPotentialDuplicates($lead)->isNotEmpty();
    }

    /**
     * Find duplicate leads based on email addresses.
     *
     * @param Lead $lead
     * @return Collection
     */
    private function findEmailDuplicates($lead): Collection
    {
        return $this->findJsonFieldDuplicates($lead, 'emails');
    }

    /**
     * Find duplicate leads based on phone numbers.
     *
     * @param Lead $lead
     * @return Collection
     */
    private function findPhoneDuplicates($lead): Collection
    {
        return $this->findJsonFieldDuplicates($lead, 'phones');
    }

    /**
     * Find duplicate leads based on name similarity.
     * - first_name + last_name exact match
     * - last_name + married_name exact match or the other way around
     */
    private function findNameDuplicates(Lead $lead): Collection
    {
        if (empty($lead->first_name) && empty($lead->last_name)) {
            return collect();
        }

        try {
            $query = LeadModel::with(['stage', 'pipeline', 'user'])
                ->where('id', '!=', $lead->id);

            // Build name matching conditions
            $this->addNameMatchConditions($query, $lead);

            return $query->get();
        } catch (Exception $e) {
            Log::error('Error searching for name duplicates: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Get name variations for similarity matching.
     */
    private function getNameVariations(String $name): array
    {
        $variations = [$name];

        // Common nickname variations
        $nicknameMap = [
            'John' => ['Johnny', 'Jon', 'Jack'],
            'Johnny' => ['John', 'Jon'],
            'Jon' => ['John', 'Johnny'],
            'Jack' => ['John', 'Jackson'],
            'William' => ['Will', 'Bill', 'Billy'],
            'Will' => ['William', 'Bill'],
            'Bill' => ['William', 'Will', 'Billy'],
            'Billy' => ['William', 'Bill'],
            'Robert' => ['Bob', 'Rob', 'Bobby'],
            'Bob' => ['Robert', 'Bobby'],
            'Rob' => ['Robert', 'Bobby'],
            'Bobby' => ['Robert', 'Bob'],
            'Richard' => ['Rick', 'Dick', 'Rich'],
            'Rick' => ['Richard', 'Rich'],
            'Rich' => ['Richard', 'Rick'],
            'Michael' => ['Mike', 'Mickey'],
            'Mike' => ['Michael', 'Mickey'],
            'Mickey' => ['Michael', 'Mike'],
            'David' => ['Dave', 'Davey'],
            'Dave' => ['David', 'Davey'],
            'Davey' => ['David', 'Dave'],
            'Christopher' => ['Chris', 'Christie'],
            'Chris' => ['Christopher', 'Christie'],
            'Christie' => ['Christopher', 'Chris'],
            'Elizabeth' => ['Liz', 'Beth', 'Betty', 'Lizzy'],
            'Liz' => ['Elizabeth', 'Beth', 'Betty'],
            'Beth' => ['Elizabeth', 'Liz', 'Betty'],
            'Betty' => ['Elizabeth', 'Liz', 'Beth'],
            'Lizzy' => ['Elizabeth', 'Liz'],
        ];

        if (isset($nicknameMap[$name])) {
            $variations = array_merge($variations, $nicknameMap[$name]);
        }

        return array_unique($variations);
    }

    /**
     * Debug method to check lead data structure.
     */
    public function debugLeadData(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'name' => $lead->name,
            'emails_type' => gettype($lead->emails),
            'emails_value' => $lead->emails,
            'phones_type' => gettype($lead->phones),
            'phones_value' => $lead->phones,
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
        ];
    }

    /**
     * Merge leads - keep the primary lead and archive others.
     *
     * @param int $primaryLeadId
     * @param array $duplicateLeadIds
     * @param array $fieldMappings
     * @return Lead
     */
    public
    function mergeLeads($primaryLeadId, $duplicateLeadIds, $fieldMappings = [])
    {
        $primaryLead = $this->findOrFail($primaryLeadId);
        $duplicateLeads = $this->findWhereIn('id', $duplicateLeadIds);

        // Start transaction
        DB::beginTransaction();

        try {
            // Apply field mappings to primary lead
            if (!empty($fieldMappings)) {
                $updateData = [];
                $addressSourceLeadId = null;

                foreach ($fieldMappings as $field => $sourceLeadId) {
                    if ($sourceLeadId != $primaryLeadId) {
                        $sourceLead = $duplicateLeads->firstWhere('id', $sourceLeadId);

                        if ($field === 'address') {
                            // Handle address separately - we need to merge the full address data
                            $addressSourceLeadId = $sourceLeadId;
                        } elseif ($sourceLead && !empty($sourceLead->$field)) {
                            $updateData[$field] = $sourceLead->$field;
                        }
                    }
                }

                if (!empty($updateData)) {
                    $primaryLead->update($updateData);
                }

                // Handle address merge separately
                if ($addressSourceLeadId) {
                    $this->mergeAddress($primaryLead, $duplicateLeads->firstWhere('id', $addressSourceLeadId));
                }
            }

            // Transfer activities from duplicate leads to primary lead
            foreach ($duplicateLeads as $duplicateLead) {
                try {
                    // Add system activity for removed duplicate lead
                    $this->addSystemActivity($primaryLead, $duplicateLead);
                } catch (Exception $e) {
                    Log::warning('Error adding system activity for duplicate removal: ' . $e->getMessage());
                }
                try {
                    // Transfer emails (hasMany relationship)
                    $duplicateLead->emails()->update(['lead_id' => $primaryLeadId]);
                } catch (Exception $e) {
                    Log::warning('Error transferring emails during merge: ' . $e->getMessage());
                }

                try {
                    // Add a note about the merge
                    $this->addMergeNote($primaryLead, $duplicateLead);
                } catch (Exception $e) {
                    Log::warning('Error adding merge note: ' . $e->getMessage());
                }

                // Archive the duplicate lead (soft delete or mark as archived)
                $duplicateLead->delete();
            }

            DB::commit();

            return $primaryLead->fresh();

        } catch (Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Merge address data from source lead to primary lead
     */
    private function mergeAddress($primaryLead, $sourceLead): void
    {
        if (!$sourceLead || !$sourceLead->address) {
            return;
        }

        $sourceAddress = $sourceLead->address;

        // Get or create address for primary lead
        $primaryAddress = $primaryLead->address;

        if ($primaryAddress) {
            // Update existing address with source address data
            $primaryAddress->update([
                'street' => $sourceAddress->street,
                'house_number' => $sourceAddress->house_number,
                'house_number_suffix' => $sourceAddress->house_number_suffix,
                'postal_code' => $sourceAddress->postal_code,
                'city' => $sourceAddress->city,
                'state' => $sourceAddress->state,
                'country' => $sourceAddress->country,
                'updated_by' => auth()->id(),
            ]);
        } else {
            // Create new address for primary lead
            $primaryLead->address()->create([
                'lead_id' => $primaryLead->id,
                'street' => $sourceAddress->street,
                'house_number' => $sourceAddress->house_number,
                'house_number_suffix' => $sourceAddress->house_number_suffix,
                'postal_code' => $sourceAddress->postal_code,
                'city' => $sourceAddress->city,
                'state' => $sourceAddress->state,
                'country' => $sourceAddress->country,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
        }

        Log::info('Address merged successfully', [
            'primary_lead_id' => $primaryLead->id,
            'source_lead_id' => $sourceLead->id,
            'merged_address' => [
                'street' => $sourceAddress->street,
                'house_number' => $sourceAddress->house_number,
                'postal_code' => $sourceAddress->postal_code,
                'city' => $sourceAddress->city,
            ]
        ]);
    }

    private
    function addSystemActivity($primaryLead, $duplicateLead): void
    {
        // Create a system activity for audit purposes
        $activityData = [
            'title' => 'System: Duplicate Lead Removed',
            'comment' => "Removed duplicate lead \"{$duplicateLead->title}\" (ID: {$duplicateLead->id}) during merge operation.",
            'type' => 'system',
            'is_done' => 1,
            'user_id' => auth()->id() ?? 1,
        ];

        $activity = app('Webkul\Activity\Repositories\ActivityRepository')->create($activityData);

        // Attach the activity to the primary lead for audit trail
        $primaryLead->activities()->attach($activity->id);

        Log::info('System activity created for duplicate removal', [
            'primary_lead_id' => $primaryLead->id,
            'removed_duplicate_id' => $duplicateLead->id,
            'removed_duplicate_title' => $duplicateLead->title,
            'activity_id' => $activity->id,
            'created_by' => auth()->id() ?? 1,
        ]);
    }

    /**
     * Add a note about the lead merge.
     *
     * @param Lead $primaryLead
     * @param Lead $duplicateLead
     * @return void
     */
    private
    function addMergeNote($primaryLead, $duplicateLead)
    {
        // Create an activity note about the merge
        $activityData = [
            'title' => 'Lead Merged',
            'comment' => "Lead #{$duplicateLead->id} ({$duplicateLead->title}) was merged into this lead.",
            'type' => 'note',
            'is_done' => 1,
            'user_id' => auth()->id() ?? 1,
        ];

        $activity = app('Webkul\Activity\Repositories\ActivityRepository')->create($activityData);

        // Attach the activity to the primary lead
        $primaryLead->activities()->attach($activity->id);
    }

    /**
     * Check if person data contains valid information
     */
    private function hasValidPersonData(array $personData): bool
    {
        // If person has an ID, it's valid (existing person)
        if (!empty($personData['id']) && is_numeric($personData['id'])) {
            return true;
        }

        // For new persons, check if they have valid data
        if (!empty($personData['name'])) {
            return true;
        }

        if (!empty($personData['emails'])) {
            foreach ($personData['emails'] as $email) {
                if (!empty($email['value'])) {
                    return true;
                }
            }
        }

        if (!empty($personData['phones'])) {
            foreach ($personData['phones'] as $number) {
                if (!empty($number['value'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Create anamnesis for a lead when first person is attached.
     */
    private function createAnamnesisForLead(Lead $lead): void
    {
        // Check if anamnesis already exists
        if ($lead->anamnesis) {
            return;
        }

        $currentUserId = auth()->id() ?? $lead->user_id ?? 1;

        try {
            // Get the first attached person for anamnesis
            $firstPersonId = \DB::table('lead_persons')->where('lead_id', $lead->id)->value('person_id');
            
            \App\Models\Anamnesis::create([
                'id' => \Illuminate\Support\Str::uuid(),
                'lead_id' => $lead->id,
                'name' => 'Anamnesis voor ' . $lead->name,
                'person_id' => $firstPersonId,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to create anamnesis for lead: ' . $e->getMessage(), [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete anamnesis for a lead when all persons are removed.
     */
    private function deleteAnamnesisForLead(Lead $lead): void
    {
        try {
            if ($lead->anamnesis) {
                $lead->anamnesis->delete();
            }
        } catch (Exception $e) {
            Log::error('Failed to delete anamnesis for lead: ' . $e->getMessage(), [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generic helper to find duplicates based on JSON field values.
     */
    private function findJsonFieldDuplicates(Lead $lead, string $fieldName): Collection
    {
        $duplicates = collect();
        $fieldData = $lead->$fieldName;

        // Handle case where field might be a string or null
        if (is_string($fieldData)) {
            $fieldData = json_decode($fieldData, true) ?: [];
        }

        if (is_array($fieldData) && !empty($fieldData)) {
            foreach ($fieldData as $item) {
                if (is_array($item) && !empty($item['value'])) {
                    try {
                        $query = LeadModel::with(['stage', 'pipeline', 'user'])
                            ->where('id', '!=', $lead->id);
                            
                        if (DB::getDriverName() === 'sqlite') {
                            $query->where($fieldName, 'LIKE', '%"' . $item['value'] . '"%');
                        } else {
                            $query->whereJsonContains($fieldName, [['value' => $item['value']]]);
                        }

                        $duplicates = $duplicates->merge($query->get());
                    } catch (Exception $e) {
                        Log::error("Error searching for {$fieldName} duplicates: " . $e->getMessage());
                    }
                }
            }
        }

        return $duplicates;
    }

    /**
     * Add name matching conditions to query.
     */
    private function addNameMatchConditions($query, Lead $lead): void
    {
        $query->where(function ($q) use ($lead) {
            // Exact match for full name
            if (!empty($lead->first_name) && !empty($lead->last_name)) {
                $q->orWhere(function ($subQuery) use ($lead) {
                    $subQuery->where('first_name', $lead->first_name)
                        ->where('last_name', $lead->last_name);
                });

                // Nickname variations
                $firstNameVariations = $this->getNameVariations($lead->first_name);
                if (count($firstNameVariations) > 1) {
                    $q->orWhere(function ($subQuery) use ($lead, $firstNameVariations) {
                        $subQuery->whereIn('first_name', $firstNameVariations)
                            ->where('last_name', $lead->last_name);
                    });
                }

                // Married name confusion checks
                if (!empty($lead->married_name)) {
                    $q->orWhere(function ($subQuery) use ($lead) {
                        $subQuery->where('first_name', $lead->first_name)
                            ->where('married_name', $lead->last_name);
                    })->orWhere(function ($subQuery) use ($lead) {
                        $subQuery->where('first_name', $lead->first_name)
                            ->where('last_name', $lead->married_name);
                    });
                }
            }
        });
    }
}
