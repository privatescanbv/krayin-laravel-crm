<?php

namespace App\Http\Requests\Api;

class PatientNotificationsIndexRequest extends PatientPaginatedIndexRequest
{
    protected function perPageMax(): int
    {
        return 10;
    }
}
