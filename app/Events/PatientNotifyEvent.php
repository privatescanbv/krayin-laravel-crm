<?php

namespace App\Events;

use App\Enums\NotificationReferenceType;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PatientNotifyEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $patientId,
        public string $type,
        public string $title,
        public string $summary,
        public NotificationReferenceType $referenceType,
        public int|string $referenceId,
        public bool $dismissable = false,
        public ?int $userId = null
    ) {}
}
