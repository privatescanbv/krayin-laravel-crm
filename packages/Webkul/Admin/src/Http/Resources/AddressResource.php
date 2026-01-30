<?php

namespace Webkul\Admin\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     */
    public function toArray(Request $request):array
    {
        return [
            'id' => $this->id,

            // Address fields
            'street' => $this->street,
            'house_number' => $this->house_number,
            'house_number_suffix' => $this->house_number_suffix,
            'postal_code' => $this->postal_code,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,

            // Computed attributes
            'full_address' => $this->full_address,

            // Timestamps
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
        ];
    }
}
