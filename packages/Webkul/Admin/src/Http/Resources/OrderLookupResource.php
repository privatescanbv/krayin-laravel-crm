<?php

namespace Webkul\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderLookupResource extends JsonResource
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
            'id'                => $this->id,
            'order_number'      => $this->order_number,
            'title'             => $this->title,

            'pipeline_stage_id' => $this->pipeline_stage_id,
            'stage'             => $this->when(
                $this->relationLoaded('stage'),
                fn () => $this->stage ? [
                    'id'   => $this->stage->id,
                    'name' => $this->stage->name,
                ] : null
            ),
            'user_id'           => $this->user_id,
            'user'              => $this->when(
                $this->relationLoaded('user'),
                fn () => $this->user ? [
                    'id'   => $this->user->id,
                    'name' => $this->user->name,
                ] : null
            ),

            'sales_lead_id'     => $this->sales_lead_id,
        ];
    }
}
