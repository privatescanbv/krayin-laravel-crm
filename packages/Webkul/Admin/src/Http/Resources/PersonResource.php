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
        $data = [
            'id'              => $this->id,
            'name'            => $this->name,
            'emails'          => $this->emails,
            'contact_numbers' => $this->contact_numbers,
            'organization'    => new OrganizationResource($this->organization),
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];

        // Include match score data if present
        if (isset($this->match_score)) {
            $data['match_score'] = $this->match_score;
        }
        
        if (isset($this->match_score_percentage)) {
            $data['match_score_percentage'] = $this->match_score_percentage;
        }

        return $data;
    }
}
