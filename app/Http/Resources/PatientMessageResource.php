<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'person_id'   => $this->person_id,
            'sender_type' => $this->sender_type,
            'sender_id'   => $this->sender_id,
            'body'        => $this->body,
            'is_read'     => $this->is_read,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
            'sender'      => $this->whenLoaded('sender'),
        ];
    }
}
