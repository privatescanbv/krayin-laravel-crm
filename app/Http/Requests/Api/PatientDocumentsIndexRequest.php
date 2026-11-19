<?php

namespace App\Http\Requests\Api;

class PatientDocumentsIndexRequest extends PatientPaginatedIndexRequest
{
    protected function additionalRules(): array
    {
        return [
            // Optional filters
            'order_id' => ['sometimes', 'integer', 'min:1', 'exists:orders,id'],
            'type'     => ['sometimes', 'string', 'max:255'],
        ];
    }

    protected function additionalQueryParameters(): array
    {
        return [
            'order_id' => [
                'description' => 'Optional: limit documents to a single Order id.',
                'example'     => 987,
            ],
            'type' => [
                'description' => 'Optional: one or more document_types ids, comma-separated.',
                'example'     => '2,5',
            ],
        ];
    }
}
