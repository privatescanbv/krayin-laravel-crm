<?php

namespace App\Http\Requests\Api;

class PatientAppointmentsIndexRequest extends PatientPaginatedIndexRequest
{
    protected function additionalRules(): array
    {
        return [
            'filter' => ['sometimes', 'string', 'in:future,past'],
        ];
    }

    protected function additionalQueryParameters(): array
    {
        return [
            'filter' => [
                'description' => 'Filter appointments. Allowed values: future, past.',
                'example'     => 'future',
            ],
        ];
    }
}
