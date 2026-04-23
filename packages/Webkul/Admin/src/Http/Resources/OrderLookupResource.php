<?php

namespace Webkul\Admin\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderLookupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Minimal resource for lookup/search operations to avoid N+1 queries.
     *
     * @param  Request
     * @return array
     */
    public function toArray($request)
    {
        $parts = array_filter([
            $this->order_number !== null && $this->order_number !== '' ? (string) $this->order_number : null,
            $this->title !== null && $this->title !== '' ? (string) $this->title : null,
        ]);
        $name = $parts !== [] ? implode(' — ', $parts) : 'Order #'.$this->id;

        return [
            'id'                => $this->id,
            'name'              => $name,
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

            'subtitle'          => $this->when(
                $this->relationLoaded('stage'),
                fn () => $this->stage?->name
            ),
        ];
    }
}
