<?php

namespace Webkul\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadLookupResource extends JsonResource
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
            'first_name'           => $this->first_name,
            'last_name'            => $this->last_name,
            'lastname_prefix'      => $this->lastname_prefix,
            'married_name'         => $this->married_name,
            'married_name_prefix'  => $this->married_name_prefix,
            'emails'               => is_array($this->emails) ? $this->emails : [],
            'phones'               => is_array($this->phones) ? $this->phones : [],
            
            // Minimal relationship data - only IDs and names, no nested relationships
            'lead_pipeline_stage_id' => $this->lead_pipeline_stage_id,
            'stage'                => $this->when(
                $this->relationLoaded('stage'),
                fn() => $this->stage ? [
                    'id' => $this->stage->id,
                    'name' => $this->stage->name,
                ] : null
            ),
            'user_id'              => $this->user_id,
            'user'                 => $this->when(
                $this->relationLoaded('user'),
                fn() => $this->user ? [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ] : null
            ),
            
            // IDs only for other relationships
            'lead_source_id'       => $this->lead_source_id,
            'lead_type_id'         => $this->lead_type_id,
            'lead_pipeline_id'     => $this->lead_pipeline_id,
            'lead_channel_id'      => $this->lead_channel_id,
            'department_id'        => $this->department_id,
            'organization_id'      => $this->organization_id,
        ];
    }
}

