<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array|mixed $resource
 */
class PatientNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $reference = data_get($this->resource, 'reference', []);

        return [
            'id'          => (int) data_get($this->resource, 'id'),
            'type'        => (string) data_get($this->resource, 'type', 'document'),
            'dismissable' => (bool) data_get($this->resource, 'dismissable', false),
            'title'       => (string) data_get($this->resource, 'title', ''),
            'summary'     => data_get($this->resource, 'summary'),
            'reference'   => [
                'type' => (string) data_get($reference, 'type', 'document'),
                'id'   => (int) data_get($reference, 'id'),
            ],
            'created_at' => (string) data_get($this->resource, 'created_at'),
            'read'       => (bool) data_get($this->resource, 'read', false),
        ];
    }
}
