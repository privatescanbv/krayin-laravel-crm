<?php

namespace App\Http\Requests\Api;

use App\Enums\AppointmentTimeFilter;

class PatientAppointmentsIndexRequest extends PatientPaginatedIndexRequest
{
    /**
     * Return the validated filter as a typed enum, or null when absent.
     */
    public function timeFilter(): ?AppointmentTimeFilter
    {
        $value = $this->validated('filter');

        return $value ? AppointmentTimeFilter::from($value) : null;
    }

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
