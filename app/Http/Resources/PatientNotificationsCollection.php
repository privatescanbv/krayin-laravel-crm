<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PatientNotificationsCollection extends PatientPaginatedCollection
{
    public static $wrap = null;

    public $collects = PatientNotificationResource::class;

    public function toArray(Request $request): array
    {
        return [
            'notifications' => $this->collection,
        ];
    }
}
