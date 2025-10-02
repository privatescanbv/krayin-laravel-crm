<?php

namespace Webkul\Lead\Repositories;

use App\Services\LeadDuplicateCacheService;
use App\Services\Concerns\JsonDuplicateMatcher;
use Carbon\Carbon;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Address;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Core\Eloquent\Repository;
use Webkul\Lead\Contracts\Lead;
use Webkul\Lead\Models\Lead as LeadModel;

class LeadRepository extends Repository
{
    use JsonDuplicateMatcher;
    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        // Use name-related fields instead of removed 'title' column
        'first_name',
        'last_name',
        'married_name',
        'status',
        'user_id',
        'user.name',
        // Support both singular and plural relation keys for backward compatibility
        'person.name',
        'persons.name',
        // Searchable JSON/text columns
        'emails',
        'phones',
        'lead_source_id',
        'lead_type_id',
        'lead_pipeline_id',
        'lead_pipeline_stage_id',
        'created_at',
        'closed_at',
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
     * Get the cache service instance.
     */
    protected function getCacheService(): LeadDuplicateCacheService
    {
        return app(LeadDuplicateCacheService::class);
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
                DB::raw("CONCAT_WS(' ', ".DB::getTablePrefix()."leads.first_name, ".DB::getTablePrefix()."leads.last_name) as title"),
                'lead_pipelines.id as lead_pipeline_id',
                'lead_pipeline_stages.name as status',
                'lead_pipeline_stages.id as lead_pipeline_stage_id'
            )
                ->addSelect(DB::raw('DATEDIFF(' . DB::getTablePrefix() . 'leads.created_at + INTERVAL lead_pipelines.rotten_days DAY, now()) as rotten_days'))
                ->leftJoin('lead_pipelines', 'leads.lead_pipeline_id', '=', 'lead_pipelines.id')
                ->leftJoin('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
                ->when($term, function($q) use ($term) {
                    $q->whereRaw(
                        "CONCAT_WS(' ', ".DB::getTablePrefix()."leads.first_name, ".DB::getTablePrefix()."leads.last_name) like ?",
                        ["%{$term}%"]
                    );
                })
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

        // Ensure entity_type default for attribute saving
        if (!isset($data['entity_type'])) {
            $data['entity_type'] = 'leads';
        }

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

        // Handle empty organization_id
        if (empty($data['organization_id']) || !is_numeric($data['organization_id'])) {
            $data['organization_id'] = null;
        }

        $lead = parent::create(array_merge([
            'lead_pipeline_id' => 1,
            'lead_pipeline_stage_id' => 1,
            'user_id' => auth()->id() ?? 1,
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

            // Anamnesis creation is now handled by the attachPersons method
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

        // Ensure entity_type default for attribute saving
        if (!isset($data['entity_type'])) {
            $data['entity_type'] = 'leads';
        }

        // Normalize nullable foreign keys that might come as empty strings/placeholders
        foreach (['user_id', 'organization_id', 'lead_channel_id', 'lead_source_id', 'lead_type_id'] as $nullableKey) {
            if (array_key_exists($nullableKey, $data)) {
                if ($data[$nullableKey] === '' || $data[$nullableKey] === '?' || $data[$nullableKey] === null) {
                    $data[$nullableKey] = null;
                }
            }
        }

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
                // Anamnesis creation is now handled by the syncPersons method
            } elseif ($hadPersons && !$hasPersonsNow) {
                // All persons removed - delete anamnesis
                $this->deleteAnamnesisForLead($lead);
            }
        }

        return $lead;
    }

    /**
     * Find potential duplicate leads based on email, phone, and name similarity.
     * Uses caching for improved performance.
     * Filters out leads that are:
     * - Created more than 2 weeks apart
     * - In 'Won' status
     */
    public function findPotentialDuplicates($lead): Collection
    {
        try {
            // Use cache service for performance optimization
            $cacheService = $this->getCacheService();
            return $cacheService->getCachedDuplicatesWithData($lead->id);
        } catch (Exception $e) {
            Log::warning('Cache service failed, falling back to direct computation: ' . $e->getMessage());
            return $this->findPotentialDuplicatesDirectly($lead);
        }
    }

    /**
     * @param $lead
     * @return int number of duplicates found, are cached for performance
     */
    public function findNumberPotentialDuplicates($lead): int {
        $cacheService = $this->getCacheService();
        return $cacheService->getCachedDuplicates($lead->id)->count();
    }

    /**
     * Direct computation of potential duplicates (fallback method).
     * This is the original implementation used when cache fails.
     */
    public function findPotentialDuplicatesDirectly($lead): Collection
    {
        $duplicates = collect();

        try {
            // Check for email duplicates
            $emailDuplicates = $this->findDuplicatesByJsonField($lead, 'emails');
            $duplicates = $duplicates->merge($emailDuplicates);

            // Check for phone duplicates
            $phoneDuplicates = $this->findDuplicatesByJsonField($lead, 'phones');
            $duplicates = $duplicates->merge($phoneDuplicates);

            // Check for name similarity
            $nameDuplicates = $this->findDuplicatesByName($lead);
            $duplicates = $duplicates->merge($nameDuplicates);

        } catch (Exception $e) {
            Log::error('Error in duplicate detection: ' . $e->getMessage());
        }

        // Remove duplicates from the collection and apply time/status filters
        $uniqueDuplicates = $duplicates->unique('id');
        return $this->applyDuplicateFilters($lead, $uniqueDuplicates);
    }

    /**
     * Find duplicates by JSON field (emails or phones).
     *
     * @param Lead $lead The lead to check duplicates for
     * @param string $field The JSON field to check (emails or phones)
     * @return Collection Collection of duplicate leads
     */
    private function findDuplicatesByJsonField($lead, string $field): Collection
    {
        $fieldData = $lead->$field;
        if (empty($fieldData) || !is_array($fieldData)) {
            return collect();
        }

        $values = collect($fieldData)->pluck('value')->filter()->toArray();
        if (empty($values)) {
            return collect();
        }

        // Check if we're using MySQL, otherwise throw exception
        if (DB::getDriverName() !== 'mysql') {
            throw new \Exception('JSON duplicate detection is only supported on MySQL database');
        }

        // Use LIKE operator to search for values in JSON field
        $results = $this->model->where('id', '!=', $lead->id)
            ->where(function ($query) use ($field, $values) {
                foreach ($values as $value) {
                    // Search for the value in the JSON array using LIKE
                    // Try both string and integer formats
                    $query->orWhere($field, 'LIKE', '%"' . $value . '"%')
                          ->orWhere($field, 'LIKE', '%' . $value . '%');
                }
            })
            ->get();

        return $results;
    }

    /**
     * Find duplicates by name similarity.
     *
     * @param Lead $lead The lead to check duplicates for
     * @return Collection Collection of duplicate leads
     */
    private function findDuplicatesByName($lead): Collection
    {
        if (empty($lead->first_name) || empty($lead->last_name)) {
            return collect();
        }

        return $this->model->where('id', '!=', $lead->id)
            ->where('first_name', $lead->first_name)
            ->where('last_name', $lead->last_name)
            ->get();
    }

    /**
     * Apply time and status filters to potential duplicates.
     *
     * @param Lead $lead The lead to check duplicates for
     * @param Collection $duplicates Collection of potential duplicate leads
     * @return Collection Filtered collection of duplicates
     */
    private function applyDuplicateFilters($lead, Collection $duplicates): Collection
    {
        $leadCreatedAt = Carbon::parse($lead->created_at);
        $twoWeeksAgo = $leadCreatedAt->copy()->subWeeks(2);
        $twoWeeksLater = $leadCreatedAt->copy()->addWeeks(2);

        // Load stage relationships for all duplicates
        $duplicates->load('stage');

        return $duplicates->filter(function ($duplicate) use ($twoWeeksAgo, $twoWeeksLater) {
            // Filter out leads in 'Won' status
            if ($duplicate->stage && $duplicate->stage->code === 'won') {
                return false;
            }

            // Filter out leads created more than 2 weeks apart
            $duplicateCreatedAt = Carbon::parse($duplicate->created_at);
            return $duplicateCreatedAt->between($twoWeeksAgo, $twoWeeksLater);
        });
    }

    /**
     * Check if a lead has potential duplicates.
     * Uses cache for improved performance.
     */
    public function hasPotentialDuplicates(Lead $lead): bool
    {
        try {
            $cacheService = $this->getCacheService();
            return $cacheService->hasCachedDuplicates($lead->id);
        } catch (Exception $e) {
            Log::warning('Cache service failed for hasPotentialDuplicates, falling back: ' . $e->getMessage());
            return $this->findPotentialDuplicatesDirectly($lead)->isNotEmpty();
        }
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

            // Update cache after successful merge
            try {
                $cacheService = $this->getCacheService();
                $cacheService->handleLeadMerge($primaryLeadId, $duplicateLeadIds);
            } catch (Exception $e) {
                Log::warning('Failed to update cache after lead merge: ' . $e->getMessage());
            }

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

        $activity = app('Webkul\Activity\Repositories\ActivityRepository')->create(array_merge($activityData, [
            'lead_id' => $primaryLead->id,
        ]));

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

        $activity = app('Webkul\Activity\Repositories\ActivityRepository')->create(array_merge($activityData, [
            'lead_id' => $primaryLead->id,
        ]));
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

                        // Use shared matcher for robust cross-driver matching
                        $query = $this->applyJsonValueMatch($query, $fieldName, (string) $item['value']);

                        $duplicates = $duplicates->merge($query->get());
                    } catch (Exception $e) {
                        Log::error("Error searching for {$fieldName} duplicates: " . $e->getMessage());
                    }
                }
            }
        }

        return $duplicates;
    }

}
