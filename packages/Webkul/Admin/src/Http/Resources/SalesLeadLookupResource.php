<?php

namespace Webkul\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SalesLeadLookupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Minimal resource for lookup/search operations to avoid N+1 queries.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'description'          => $this->description,

            // Minimal relationship data - only IDs and names, no nested relationships
            'pipeline_stage_id'    => $this->pipeline_stage_id,
            'stage'                => $this->when(
                $this->relationLoaded('stage'),
                fn() => $this->pipelineStage ? [
                    'id'   => $this->pipelineStage->id,
                    'name' => $this->pipelineStage->name,
                ] : null
            ),
            'user_id'              => $this->user_id,
            'user'                 => $this->when(
                $this->relationLoaded('user'),
                fn() => $this->user ? [
                    'id'   => $this->user->id,
                    'name' => $this->user->name,
                ] : null
            ),

            // Other IDs for reference
            'contact_person_id'    => $this->contact_person_id,
        ];
    }
}

