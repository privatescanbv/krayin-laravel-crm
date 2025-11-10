<?php

namespace Webkul\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PersonResource extends JsonResource
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

        $data = [
            'id'              => $this->id,
            'name'            => $this->name,
            'emails'          => $this->emails,
            'phones'          => $this->phones, // Alias for compatibility
            'organization'    => $this->organization ? new OrganizationResource($this->organization) : null,
            'address'         => $this->when(
                $this->relationLoaded('address'),
                fn() => $this->address
            ), // Only include if already loaded to avoid N+1 queries
            'first_name'      => $this->first_name,
            'last_name'       => $this->last_name,
            'lastname_prefix' => $this->lastname_prefix,
            'married_name'    => $this->married_name,
            'married_name_prefix' => $this->married_name_prefix,
            'initials'        => $this->initials,
            'date_of_birth'   => $this->date_of_birth ? $this->date_of_birth->format('Y-m-d') : null,
            'gender'          => $this->gender?->value,
            'salutation'      => $this->salutation?->value,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];

        // Include match score data if present
        if (isset($this->match_score)) {
            $data['match_score'] = $this->match_score;
            $data['score'] = $this->match_score; // Alias for test compatibility
        }

        if (isset($this->match_score_percentage)) {
            $data['match_score_percentage'] = $this->match_score_percentage;
        }

        return $data;
    }
}
