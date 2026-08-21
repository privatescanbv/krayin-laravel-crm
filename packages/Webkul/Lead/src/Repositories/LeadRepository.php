<?php

namespace Webkul\Lead\Repositories;

use App\Enums\Departments;
use App\Enums\DuplicateEntityType;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStageDefaultKeys;
use App\Models\Department;
use App\Repositories\AddressRepository;
use App\Services\LeadDuplicateCacheService;
use App\Services\Concerns\JsonDuplicateMatcher;
use Carbon\Carbon;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Core\Eloquent\Repository;
use Webkul\Lead\Contracts\Lead;

class LeadRepository extends Repository
{
    use JsonDuplicateMatcher;

    /**
     * How far back duplicate leads are searched for, counted from now. Public so callers that need
     * to keep the duplicate cache warm for this same window (see RefreshDuplicateCache) can reuse it
     * instead of hardcoding the number again.
     */
    public const int DUPLICATE_SEARCH_PERIOD_WEEKS = 4;

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
        // New explicit user fields for first/last name search
        'user.first_name',
        'user.last_name',
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
        protected AttributeRepository      $attributeRepository,
        protected AttributeValueRepository $attributeValueRepository,
        Container                          $container
    )
    {
        parent::__construct($container);
    }

    /**
     * Get the cache service instance. Workaround because LeadDuplicateCacheService depends on this class
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
            'user_id' => auth()->id() ?? 1,
        ], $data));


        // Handle address data for new leads
        if (isset($data['address']) && !empty($data['address'])) {
            app(AddressRepository::class)->upsertForEntity($lead, $data['address']);
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
        Log::debug('LeadRepository update received data', [
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

            if ($stage->is_won || $stage->is_lost) {
                $data['closed_at'] = $data['closed_at'] ?? Carbon::now();
            } else {
                $data['closed_at'] = null;
            }
        }

        // Handle empty organization_id
        if (empty($data['organization_id']) || !is_numeric($data['organization_id'])) {
            $data['organization_id'] = null;
        }

        $lead = parent::update($data, $id);

        // Handle address data using central AddressRepository (with validation)
        if (isset($data['address']) && is_array($data['address'])) {
            app(AddressRepository::class)->upsertForEntity($lead, $data['address']);
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
     * - Created outside the last DUPLICATE_SEARCH_PERIOD_WEEKS weeks
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

        // Push the recency window down into the candidate queries themselves (leads.created_at is
        // indexed - see leads_created_at_idx) instead of fetching every historical match and
        // discarding old rows in PHP afterwards. applyDuplicateFilters() below re-checks the same
        // window - this only cuts down what gets scanned/fetched.
        $periodStart = Carbon::now()->subWeeks(self::DUPLICATE_SEARCH_PERIOD_WEEKS);

        // The window is pair-based: a pair is relevant when at least one of the two leads is recent.
        // So only restrict candidates by age when the lead itself already falls outside the window -
        // otherwise a fresh lead would not see the older lead it duplicates, while that older lead
        // does see the fresh one.
        $leadIsRecent = Carbon::parse($lead->created_at)->gte($periodStart);
        $withinSearchWindow = $leadIsRecent
            ? null
            : fn ($query) => $query->where('created_at', '>=', $periodStart);

        try {
            // Check for email duplicates
            $emailDuplicates = $this->findDuplicatesByJsonField($lead, 'emails', $withinSearchWindow);
            $duplicates = $duplicates->merge($emailDuplicates);

            // Check for phone duplicates
            $phoneDuplicates = $this->findDuplicatesByJsonField($lead, 'phones', $withinSearchWindow);
            $duplicates = $duplicates->merge($phoneDuplicates);

            // Check for name similarity
            $nameDuplicates = $this->findDuplicatesByName($lead, $withinSearchWindow);
            $duplicates = $duplicates->merge($nameDuplicates);

        } catch (Exception $e) {
            Log::error('Error in duplicate detection: ' . $e->getMessage());
        }

        // Remove duplicates from the collection and apply time/status filters
        $uniqueDuplicates = $duplicates->unique('id');
        return $this->applyDuplicateFilters($lead, $uniqueDuplicates);
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
        $now = Carbon::now();
        $periodStart = $now->copy()->subWeeks(self::DUPLICATE_SEARCH_PERIOD_WEEKS);

        // Closed leads hide the pair from both sides: without this the closed lead would still list
        // its counterpart while the counterpart no longer lists the closed one.
        $lead->loadMissing('stage');
        if ($this->isClosedStage($lead)) {
            return collect();
        }

        $leadIsRecent = Carbon::parse($lead->created_at)->gte($periodStart);

        return $duplicates->filter(function ($duplicate) use ($periodStart, $now, $leadIsRecent) {
            // Filter out leads in a 'Won' or 'Lost' status
            if ($this->isClosedStage($duplicate)) {
                return false;
            }

            // Recency is only required from the candidate when the lead itself is no longer recent
            // (see findPotentialDuplicatesDirectly - the window applies to the pair, not one side).
            if ($leadIsRecent) {
                return true;
            }

            $duplicateCreatedAt = Carbon::parse($duplicate->created_at);

            return $duplicateCreatedAt->between($periodStart, $now);
        });
    }

    /**
     * A lead in a won or lost stage is done - duplicates are no longer worth reporting.
     */
    private function isClosedStage($lead): bool
    {
        return (bool) ($lead->stage?->is_won || $lead->stage?->is_lost);
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
    public function mergeLeads($primaryLeadId, $duplicateLeadIds, $fieldMappings = [])
    {
        $primaryLead = $this->findOrFail($primaryLeadId);

        // A lead can never be its own duplicate; merging it into itself would soft delete it.
        $duplicateLeadIds = array_values(array_diff($duplicateLeadIds, [$primaryLeadId]));

        if (empty($duplicateLeadIds)) {
            return $primaryLead;
        }

        $this->guardAgainstMergingSalesLeads($duplicateLeadIds);

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
                        } elseif ($field === 'diagnosis_form') {
                            // One choice covers both columns, copied verbatim including null: mixing
                            // the primary's portal form with the duplicate's PDF is never what the
                            // user picked. The old values stay visible in the activity log.
                            $updateData['diagnosis_form_id'] = $sourceLead?->diagnosis_form_id;
                            $updateData['diagnoseform_pdf_url'] = $sourceLead?->diagnoseform_pdf_url;
                        } elseif ($sourceLead && !empty($sourceLead->$field)) {
                            $updateData[$field] = in_array($field, ['emails', 'phones'], true)
                                ? $this->unionContactValues($updateData[$field] ?? $primaryLead->$field, $sourceLead->$field)
                                : $sourceLead->$field;
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

            foreach ($duplicateLeads as $duplicateLead) {
                // The audit activities are written first and without a try/catch: they are the only
                // trail linking a duplicate to the lead it was merged into (see the
                // leads:repair-merge-orphans command). If they cannot be written the whole merge
                // must roll back, otherwise we create orphans nobody can trace back.
                $this->addSystemActivity($primaryLead, $duplicateLead);
                $this->addMergeNote($primaryLead, $duplicateLead);

                $this->transferLeadRelations((int) $primaryLead->id, (int) $duplicateLead->id);

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
     * Merge two [{label, value, is_default}] lists (emails/phones) instead of letting one replace the
     * other, so a merge never throws away a phone number or address. Deduplicated on value; only the
     * primary keeps its default flag.
     *
     * @param array<int, array<string, mixed>>|null $primary
     * @param array<int, array<string, mixed>>|null $source
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
     * Which of the given leads already have a sales lead (salesleads.lead_id). A duplicate with a
     * sales lead carries orders and invoicing with it, so it can never be the side that gets
     * archived by a merge. Used both by the merge guard below and by the duplicates screen to keep
     * such leads from being picked as a duplicate in the first place.
     *
     * @param array<int, int|string> $leadIds
     * @return Collection<int, int>
     */
    public function leadIdsWithSalesLead(array $leadIds): Collection
    {
        return DB::table('salesleads')
            ->whereIn('lead_id', $leadIds)
            ->pluck('lead_id')
            ->unique();
    }

    /**
     * Won leads are filtered out of the automatic duplicate list (see applyDuplicateFilters) and
     * sales leads only exist for won leads, so this should never trigger from there - it guards the
     * manual "search and merge" flow (which can inject any lead, see DuplicateController::index),
     * a stale duplicate cache, and direct repository calls.
     *
     * @param array<int, int|string> $duplicateLeadIds
     *
     * @throws Exception
     */
    private function guardAgainstMergingSalesLeads(array $duplicateLeadIds): void
    {
        $blocked = $this->leadIdsWithSalesLead($duplicateLeadIds);

        if ($blocked->isNotEmpty()) {
            throw new Exception(
                'Lead(s) '.$blocked->implode(', ').' hebben een sales (verkooptraject) en kunnen niet worden samengevoegd.'
            );
        }
    }

    /**
     * Re-point everything that hangs off the duplicate lead to the primary lead.
     *
     * The duplicate is only soft deleted, so none of the ON DELETE constraints fire and every related
     * row would silently stay behind on an invisible lead. Uses query builder throughout so soft
     * deletes are ignored - the repair command calls this for leads that are already deleted.
     */
    public function transferLeadRelations(int $primaryLeadId, int $duplicateLeadId): void
    {
        foreach (['emails', 'lead_marketing_data'] as $table) {
            DB::table($table)->where('lead_id', $duplicateLeadId)->update(['lead_id' => $primaryLeadId]);
        }

        $this->transferActivitiesSkippingDuplicates('lead_id', $primaryLeadId, $duplicateLeadId);

        $this->resolveAnamnesisConflictsBeforeLeadReassign($primaryLeadId, $duplicateLeadId);

        DB::table('anamnesis')->where('lead_id', $duplicateLeadId)->update(['lead_id' => $primaryLeadId]);

        $this->movePivotRows('lead_persons', 'person_id', $primaryLeadId, $duplicateLeadId);
        $this->movePivotRows('lead_tags', 'tag_id', $primaryLeadId, $duplicateLeadId);

        // Custom attribute values: unique (entity_type, entity_id, attribute_id), primary wins.
        $primaryAttributeIds = DB::table('attribute_values')
            ->where('entity_type', 'leads')
            ->where('entity_id', $primaryLeadId)
            ->pluck('attribute_id')
            ->all();

        DB::table('attribute_values')
            ->where('entity_type', 'leads')
            ->where('entity_id', $duplicateLeadId)
            ->whereIn('attribute_id', $primaryAttributeIds)
            ->delete();

        DB::table('attribute_values')
            ->where('entity_type', 'leads')
            ->where('entity_id', $duplicateLeadId)
            ->update(['entity_id' => $primaryLeadId]);

        // "Not a duplicate" pairs are meaningless once one side is gone; re-pointing them would
        // create a self-pair or collide with an existing pair on the primary.
        DB::table('duplicates_false_positives')
            ->where('entity_type', DuplicateEntityType::LEAD->value)
            ->where(function ($query) use ($duplicateLeadId) {
                $query->where('entity_id_1', $duplicateLeadId)
                    ->orWhere('entity_id_2', $duplicateLeadId);
            })
            ->delete();

        // Adopt the address when the primary has none and no explicit field mapping was made.
        $primaryAddressId = DB::table('leads')->where('id', $primaryLeadId)->value('address_id');
        $duplicateAddressId = DB::table('leads')->where('id', $duplicateLeadId)->value('address_id');

        if (empty($primaryAddressId) && ! empty($duplicateAddressId)) {
            DB::table('leads')->where('id', $primaryLeadId)->update(['address_id' => $duplicateAddressId]);
        }
    }

    /**
     * Move activities to the primary lead, skipping any duplicate activity that already matches
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
     * Move pivot rows to the primary lead, dropping the ones it already has so unique indexes
     * (lead_persons) and unindexed tables alike (lead_tags) never end up with duplicates.
     */
    private function movePivotRows(string $table, string $otherKey, int $primaryLeadId, int $duplicateLeadId): void
    {
        $existing = DB::table($table)->where('lead_id', $primaryLeadId)->pluck($otherKey)->all();

        DB::table($table)
            ->where('lead_id', $duplicateLeadId)
            ->whereNotIn($otherKey, $existing)
            ->update(['lead_id' => $primaryLeadId]);

        DB::table($table)->where('lead_id', $duplicateLeadId)->delete();
    }

    /**
     * Before re-pointing anamnesis rows, drop the ones that would violate unique(lead_id, person_id).
     * Newest row wins, mirroring PersonRepository::resolveAnamnesisConflictsBeforePersonReassign().
     */
    private function resolveAnamnesisConflictsBeforeLeadReassign(int $primaryLeadId, int $duplicateLeadId): void
    {
        $conflictPersonIds = DB::table('anamnesis as d')
            ->where('d.lead_id', $duplicateLeadId)
            ->whereNotNull('d.person_id')
            ->whereExists(function ($query) use ($primaryLeadId) {
                $query->selectRaw('1')
                    ->from('anamnesis as p')
                    ->whereColumn('p.person_id', 'd.person_id')
                    ->where('p.lead_id', $primaryLeadId);
            })
            ->pluck('d.person_id');

        foreach ($conflictPersonIds->unique()->all() as $personId) {
            $rows = DB::table('anamnesis')
                ->where('person_id', $personId)
                ->whereIn('lead_id', [$primaryLeadId, $duplicateLeadId])
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
     * @param Department $department
     * @return array{
     *   lead_pipeline_id: int,
     *   lead_pipeline_stage_id: int
     * }
     */
    public function mapLeadPipelineLineByDepartment(Department $department): array
    {
        $leadPipelineId = PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value;
        $leadPipelineStageId = PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_PRIVATESCAN_ID->value;
        if ($department->name == Departments::HERNIA->value) {
            $leadPipelineId = PipelineDefaultKeys::PIPELINE_HERNIA_ID->value;
            $leadPipelineStageId = PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_HERNIA_ID->value;
        }

        return [$leadPipelineId, $leadPipelineStageId];
    }

    public function resolveEmailVariablesById($leadId): array {
        return $this->resolveEmailVariables($this->find($leadId));
    }

    private function resolveEmailVariables(Lead $lead): array
    {
        // First check contact person (no need to load persons relation)
        if ($lead->hasContactPerson()) {
            $lead->load('contactPerson');
            return ['lastname' => $lead->contactPerson->last_name];
        }

        // If no contact person, check linked persons (use query directly to avoid pivot loading issues)
        $person = $lead->persons()->first();
        if (is_null($person)) {
            //resolve from given lead.
            return ['lastname' => $lead->last_name];
        }
        return ['lastname' => $person->last_name];
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

        // Prepare address data from source
        $addressData = [
            'street' => $sourceAddress->street,
            'house_number' => $sourceAddress->house_number,
            'house_number_suffix' => $sourceAddress->house_number_suffix,
            'postal_code' => $sourceAddress->postal_code,
            'city' => $sourceAddress->city,
            'state' => $sourceAddress->state,
            'country' => $sourceAddress->country,
        ];

        // Use the AddressRepository to upsert the address for the primary lead
        app(AddressRepository::class)->upsertForEntity($primaryLead, $addressData);

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
            'comment' => "Removed duplicate lead \"{$duplicateLead->name}\" (ID: {$duplicateLead->id}) during merge operation.",
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
            'removed_duplicate_title' => $duplicateLead->name,
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
            'comment' => "Lead #{$duplicateLead->id} ({$duplicateLead->name}) was merged into this lead.",
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

}
