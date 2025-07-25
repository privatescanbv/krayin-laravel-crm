<?php

namespace Webkul\Lead\Repositories;

use App\Models\Anamnesis;
use Carbon\Carbon;
use Exception;
use Illuminate\Container\Container;
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
        'person_id',
        'person.name',
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
                'persons.name as person_name',
                'leads.person_id as person_id',
                'lead_pipelines.id as lead_pipeline_id',
                'lead_pipeline_stages.name as status',
                'lead_pipeline_stages.id as lead_pipeline_stage_id'
            )
                ->addSelect(DB::raw('DATEDIFF(' . DB::getTablePrefix() . 'leads.created_at + INTERVAL lead_pipelines.rotten_days DAY, now()) as rotten_days'))
                ->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
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
     * @return \Webkul\Lead\Contracts\Lead
     */
    public function create(array $data): Lead
    {
       if (
           array_key_exists('person', $data)
           && !empty($data['person']['organization_id'])
           && (empty($data['person_id']) || empty($data['person']['id']))
       ) {
           throw new InvalidArgumentException('Een organisatie mag alleen gekoppeld worden als er ook een contactpersoon is.');
       }
        /**
         * If a person is provided, create or update the person and set the `person_id`.
         */
        if (isset($data['person']) && !empty($data['person'])) {
            // Check if there are any non-empty values in the person data
            $hasValidData = false;

            if (!empty($data['person']['name'])) {
                $hasValidData = true;
            }

            if (!empty($data['person']['emails'])) {
                foreach ($data['person']['emails'] as $email) {
                    if (!empty($email['value'])) {
                        $hasValidData = true;
                        break;
                    }
                }
            }

            if (!empty($data['person']['contact_numbers'])) {
                foreach ($data['person']['contact_numbers'] as $number) {
                    if (!empty($number['value'])) {
                        $hasValidData = true;
                        break;
                    }
                }
            }

            if ($hasValidData) {
                if (!empty($data['person']['id'])) {
                    $person = $this->personRepository->findOrFail($data['person']['id']);
                } else {
                    $person = $this->personRepository->create(array_merge($data['person'], [
                        'entity_type' => 'persons',
                    ]));
                }

                $data['person_id'] = $person->id;
            }
        }

        if (empty($data['expected_close_date'])) {
            $data['expected_close_date'] = null;
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

        // Always create an anamnesis for new leads
        $currentUserId = auth()->id() ?? $lead->user_id ?? 1;

        try {
            Anamnesis::create([
                'id' => Str::uuid(),
                'lead_id' => $lead->id,
                'name' => 'Anamnesis voor ' . $lead->title,
                'user_id' => $currentUserId,
            ]);
        } catch (Exception $e) {
            // Log the error but don't fail the lead creation
            Log::error('Failed to create anamnesis for lead: ' . $e->getMessage(), [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $lead;
    }

    /**
     * Update.
     *
     * @param int $id
     * @param array|\Illuminate\Database\Eloquent\Collection $attributes
     * @return \Webkul\Lead\Contracts\Lead
     */
    public function update(array $data, $id, $attributes = []): Lead
    {
        if (
            array_key_exists('person', $data)
            && !empty($data['person']['organization_id'])
            && (empty($data['person_id']) || empty($data['person']['id']))
        ) {
            throw new InvalidArgumentException('Een organisatie mag alleen gekoppeld worden als er ook een contactpersoon is.');
        }
        /**
         * If a person is provided, create or update the person and set the `person_id`.
         * Be cautious, as a lead can be updated without providing person data.
         * For example, in the lead Kanban section, when switching stages, only the stage will be updated.
         */
        if (isset($data['person'])) {
            $personData = $data['person'];
            $values = [];
            array_walk_recursive($personData, function ($v, $k) use (&$values) {
                if ($k !== 'label') {
                    $values[] = $v;
                }
            });
            $personContainsData = !empty(array_filter($values));
            $data['person'] = $personData;
            if ($personContainsData) {
                if (!empty($data['person']['id'])) {
                    $person = $this->personRepository->findOrFail($data['person']['id']);
                } else {
                    $person = $this->personRepository->create(array_merge($data['person'], [
                        'entity_type' => 'persons',
                    ]));
                }

                $data['person_id'] = $person->id;
            }
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
        if (empty($data['person_id'])) {
            unset($data['person_id']);
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

        return $lead;
    }

    /**
     * Find potential duplicate leads based on email, phone, and name similarity.
     *
     * @param \Webkul\Lead\Contracts\Lead $lead
     * @return \Illuminate\Support\Collection
     */
    public function findPotentialDuplicates($lead)
    {
        $duplicates = collect();

        try {
            // Check for email duplicates
            $emails = $lead->emails;

            // Handle case where emails might be a string or null
            if (is_string($emails)) {
                $emails = json_decode($emails, true) ?: [];
            }

            if (is_array($emails) && !empty($emails)) {
                foreach ($emails as $email) {
                    if (is_array($email) && !empty($email['value'])) {
                        try {
                            // Use different JSON query approaches for different databases
                            $query = LeadModel::with(['person', 'stage', 'pipeline', 'user'])
                                ->where('id', '!=', $lead->id);
                            if (DB::getDriverName() === 'sqlite') {
                                // SQLite: Use LIKE for JSON searching
                                $query->where('emails', 'LIKE', '%"' . $email['value'] . '"%');
                            } else {
                                // MySQL/PostgreSQL: Use whereJsonContains
                                $query->whereJsonContains('emails', [['value' => $email['value']]]);
                            }

                            $emailDuplicates = $query->get();

                            $duplicates = $duplicates->merge($emailDuplicates);
                        } catch (Exception $e) {
                            Log::warning('Error searching for email duplicates: ' . $e->getMessage());
                        }
                    }
                }
            }

            // Check for phone duplicates
            $phones = $lead->phones;

            // Handle case where phones might be a string or null
            if (is_string($phones)) {
                $phones = json_decode($phones, true) ?: [];
            }

            if (is_array($phones) && !empty($phones)) {
                foreach ($phones as $phone) {
                    if (is_array($phone) && !empty($phone['value'])) {
                        try {
                            // Use different JSON query approaches for different databases
                            $query = LeadModel::with(['person', 'stage', 'pipeline', 'user'])
                                ->where('id', '!=', $lead->id);
                            if (DB::getDriverName() === 'sqlite') {
                                // SQLite: Use JSON_EXTRACT or LIKE for JSON searching
                                $query->where('phones', 'LIKE', '%"' . $phone['value'] . '"%');
                            } else {
                                // MySQL/PostgreSQL: Use whereJsonContains
                                $query->whereJsonContains('phones', [['value' => $phone['value']]]);
                            }

                            $phoneDuplicates = $query->get();

                            $duplicates = $duplicates->merge($phoneDuplicates);
                        } catch (Exception $e) {
                            Log::warning('Error searching for phone duplicates: ' . $e->getMessage());
                        }
                    }
                }
            }
        } catch (Exception $e) {
            Log::error('Error in email/phone duplicate detection: ' . $e->getMessage());
        }

        // Check for name similarity (first + last name)
        if (!empty($lead->first_name) && !empty($lead->last_name)) {
            try {
                $nameDuplicates = LeadModel::with(['person', 'stage', 'pipeline', 'user'])
                    ->where('id', '!=', $lead->id)
                    ->where(function ($query) use ($lead) {
                        // Exact match for full name
                        $query->where(function ($subQuery) use ($lead) {
                            $subQuery->where(function ($query) use ($lead) {
                                // Exact match for full name
                                $query->where(function ($subQuery) use ($lead) {
                                    $subQuery->where('first_name', $lead->first_name)
                                        ->where('last_name', $lead->last_name);
                                })
                                    // Or check for common nickname variations
                                    ->orWhere(function ($subQuery) use ($lead) {
                                        $firstNameVariations = $this->getNameVariations($lead->first_name);
                                        if (count($firstNameVariations) > 1) {
                                            $subQuery->whereIn('first_name', $firstNameVariations)
                                                ->where('last_name', $lead->last_name);
                                        }
                                    });
                            });
                        });
                    })
                    ->get();

                $duplicates = $duplicates->merge($nameDuplicates);
            } catch (Exception $e) {
                Log::warning('Error searching for name duplicates: ' . $e->getMessage());
            }
        }

        // Remove duplicates from the collection and return unique leads
        return $duplicates->unique('id');
    }

    /**
     * Check if a lead has potential duplicates.
     *
     * @param \Webkul\Lead\Contracts\Lead $lead
     * @return bool
     */
    public
    function hasPotentialDuplicates($lead)
    {
        return $this->findPotentialDuplicates($lead)->isNotEmpty();
    }

    /**
     * Get name variations for similarity matching.
     *
     * @param string $name
     * @return array
     */
    private
    function getNameVariations($name)
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
     *
     * @param \Webkul\Lead\Contracts\Lead $lead
     * @return array
     */
    public
    function debugLeadData($lead)
    {
        return [
            'id' => $lead->id,
            'title' => $lead->title,
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
     * @return \Webkul\Lead\Contracts\Lead
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

                foreach ($fieldMappings as $field => $sourceLeadId) {
                    if ($sourceLeadId != $primaryLeadId) {
                        $sourceLead = $duplicateLeads->firstWhere('id', $sourceLeadId);
                        if ($sourceLead && !empty($sourceLead->$field)) {
                            $updateData[$field] = $sourceLead->$field;
                        }
                    }
                }

                if (!empty($updateData)) {
                    $primaryLead->update($updateData);
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
     * @param \Webkul\Lead\Contracts\Lead $primaryLead
     * @param \Webkul\Lead\Contracts\Lead $duplicateLead
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
}
