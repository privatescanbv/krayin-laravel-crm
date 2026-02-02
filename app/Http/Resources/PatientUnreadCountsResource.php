<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientUnreadCountsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'new_messages_count'     => (int) data_get($this->resource, 'new_messages_count', 0),
            'new_appointments_count' => (int) data_get($this->resource, 'new_appointments_count', 0),
            'new_docs_count'         => (int) data_get($this->resource, 'new_docs_count', 0),
        ];
    }
}
