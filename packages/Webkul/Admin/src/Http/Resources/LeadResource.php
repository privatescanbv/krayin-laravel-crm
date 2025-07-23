<?php

namespace Webkul\Admin\Http\Resources;

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
        return [
            'id'                   => $this->id,
            'title'                => $this->title,
            'status'               => $this->status,
            'expected_close_date'  => $this->expected_close_date,
            'rotten_days'          => $this->rotten_days,
            'closed_at'            => $this->closed_at,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
            'person'               => $this->person ? new PersonResource($this->person) : null,
            'user'                 => $this->user ? new UserResource($this->user) : null,
            'type'                 => $this->type ? new TypeResource($this->type) : null,
            'source'               => $this->source ? new SourceResource($this->source) : null,
            'pipeline'             => $this->pipeline ? new PipelineResource($this->pipeline) : null,
            'stage'                => $this->stage ? new StageResource($this->stage) : null,
            'tags'                 => TagResource::collection($this->tags),
            'open_activities_count'=> $this->open_activities_count,
            'has_duplicates'       => $this->getSafeDuplicateStatus(),
            'duplicates_count'     => $this->getSafeDuplicateCount(),
            'first_name'           => $this->first_name,
            'last_name'            => $this->last_name,
            'emails'               => is_array($this->emails) ? $this->emails : [],
            'phones'               => is_array($this->phones) ? $this->phones : [],
            'unread_emails_count'  => $this->unread_emails_count,
            'days_until_due_date'  => $this->days_until_due_date,
        ];
    }

    /**
     * Safely get duplicate status without causing errors.
     */
    private function getSafeDuplicateStatus(): bool
    {
        try {
            // Only check for duplicates if the lead has basic required data
            if (!$this->id || !$this->title) {
                return false;
            }

            return $this->hasPotentialDuplicates();
        } catch (\Exception $e) {
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
            if (!$this->id || !$this->title) {
                return 0;
            }

            return $this->getPotentialDuplicatesCount();
        } catch (\Exception $e) {
            Log::warning('Error getting duplicate count in LeadResource: ' . $e->getMessage());
            return 0;
        }
    }
}
