<?php

namespace Webkul\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductGroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'             => $this->id,
            'name'           => $this->path ?? $this->name, // Use path (hierarchical) for display, fallback to name
            'label'          => $this->path ?? $this->name, // Alias for entity selector compatibility
            'path'           => $this->path,
            'description'    => $this->description,
            'products_count' => $this->products->count(),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
