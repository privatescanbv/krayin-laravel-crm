<?php

namespace Webkul\Admin\Http\Resources;

use Exception;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class LeadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        // Lightweight payload for kanban: include essential fields but avoid heavy duplicate checks
        if ($request->query('kanban')) {
            return [
                'id'                   => $this->id,
                'name'                 => $this->name,
                'first_name'           => $this->first_name,
                'last_name'            => $this->last_name,
                'lastname_prefix'      => $this->lastname_prefix,
                'married_name'         => $this->married_name,
                'married_name_prefix'  => $this->married_name_prefix,
                'status'               => $this->status,
                'lost_reason'          => $this->lost_reason,
                'expected_close_date'  => $this->expected_close_date?->format('Y-m-d'),
                'closed_at'            => $this->closed_at?->format('Y-m-d H:i:s'),
                'rotten_days'          => $this->rotten_days,
                'created_at'           => $this->created_at?->format('Y-m-d H:i:s'),

                // Essential relationships for kanban display
                'persons'              => PersonResource::collection($this->persons ?? []),
                'persons_count'        => $this->persons_count ?? 0,
                'has_multiple_persons' => $this->hasMultiplePersons(),
                'stage'                => $this->stage ? new StageResource($this->stage) : null,

                // Computed/lightweight attributes
                'days_until_due_date'  => $this->days_until_due_date,

                // IDs for relationships (used for navigation and updates)
                'lead_pipeline_id'     => $this->lead_pipeline_id,
                'lead_pipeline_stage_id'=> $this->lead_pipeline_stage_id,

                // Optional counters (keep cheap defaults if not precomputed)
                'open_activities_count'=> $this->open_activities_count ?? 0,
                'unread_emails_count'  => $this->unread_emails_count ?? 0,

                // Disable expensive duplicate checks for kanban performance
                'has_duplicates'       => false, // Skip expensive duplicate detection
                'duplicates_count'     => 0,     // Skip expensive duplicate counting
            ];
        }

        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'description'          => $this->description,
            'lead_value'           => $this->lead_value,
            'status'               => $this->status,
            'lost_reason'          => $this->lost_reason,
            'expected_close_date'  => $this->expected_close_date?->format('Y-m-d'),
            'closed_at'            => $this->closed_at?->format('Y-m-d H:i:s'),
            'rotten_days'          => $this->rotten_days,
            'created_at'           => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'           => $this->updated_at?->format('Y-m-d H:i:s'),

            // Personal information (for matching purposes)
            'salutation'           => $this->salutation,
            'first_name'           => $this->first_name,
            'last_name'            => $this->last_name,
            'lastname_prefix'      => $this->lastname_prefix,
            'married_name'         => $this->married_name,
            'married_name_prefix'  => $this->married_name_prefix,
            'initials'             => $this->initials,
            'date_of_birth'        => $this->date_of_birth?->format('Y-m-d'),
            'gender'               => $this->gender,

            // Contact information (for matching purposes)
            'emails'               => is_array($this->emails) ? $this->emails : [],
            'phones'               => is_array($this->phones) ? $this->phones : [],

            // Relationships
            'persons'              => PersonResource::collection($this->persons ?? []),
            'persons_count'        => $this->persons_count ?? 0,
            'has_multiple_persons' => $this->hasMultiplePersons(),
            'organization'         => $this->organization ? new OrganizationResource($this->organization) : null,
            'user'                 => $this->user ? new UserResource($this->user) : null,
            'type'                 => $this->type ? new TypeResource($this->type) : null,
            'source'               => $this->source ? new SourceResource($this->source) : null,
            'pipeline'             => $this->pipeline ? new PipelineResource($this->pipeline) : null,
            'stage'                => $this->stage ? new StageResource($this->stage) : null,
            'address'              => $this->address ? new AddressResource($this->address) : null,
            'tags'                 => TagResource::collection($this->tags),

            // Computed attributes
            'open_activities_count'=> $this->open_activities_count ?? 0,
            'unread_emails_count'  => $this->unread_emails_count ?? 0,
            'days_until_due_date'  => $this->days_until_due_date,
            'has_duplicates'       => $this->getSafeDuplicateStatus(),
            'duplicates_count'     => $this->getSafeDuplicateCount(),

            // IDs for relationships
            'user_id'              => $this->user_id,
            'person_id'            => $this->person_id,
            'lead_source_id'       => $this->lead_source_id,
            'lead_type_id'         => $this->lead_type_id,
            'lead_pipeline_id'     => $this->lead_pipeline_id,
            'lead_pipeline_stage_id'=> $this->lead_pipeline_stage_id,
            'lead_channel_id'      => $this->lead_channel_id,
            'department_id'        => $this->department_id,
            'combine_order'        => $this->combine_order,
            'created_by'           => $this->created_by,
            'updated_by'           => $this->updated_by,
        ];
    }

    /**
     * Safely get duplicate status without causing errors.
     */
    private function getSafeDuplicateStatus(): bool
    {
        try {
            // Only check for duplicates if the lead has basic required data
            if (!$this->id || !$this->name) {
                return false;
            }

            return $this->hasPotentialDuplicates();
        } catch (Exception $e) {
            Log::warning('Error checking duplicate status in LeadResource: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Safely get duplicate count without causing errors.
     */
    private function getSafeDuplicateCount(): int
    {
        try {
            // Only check for duplicates if the lead has basic required data
            if (!$this->id || !$this->name) {
                return 0;
            }

            return $this->getPotentialDuplicatesCount();
        } catch (\Exception $e) {
            Log::warning('Error getting duplicate count in LeadResource: ' . $e->getMessage());
            return 0;
        }
    }
}
