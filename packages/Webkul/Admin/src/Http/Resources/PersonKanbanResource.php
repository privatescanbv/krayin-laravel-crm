<?php

namespace Webkul\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PersonKanbanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        // Handle null person gracefully
        if (!$this->resource) {
            return null;
        }

        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'organization'    => $this->organization ? new OrganizationResource($this->organization) : null,
        ];
    }
}
