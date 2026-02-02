<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PatientNotificationsCollection extends PatientPaginatedCollection
{
    public $collects = PatientNotificationResource::class;

    public function toArray(Request $request): array
    {
        return [
            'notifications' => $this->collection,
        ];
    }
}
