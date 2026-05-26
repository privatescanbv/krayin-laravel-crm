<?php

namespace App\Events;

use App\Enums\FormStatus;
use App\Enums\FormType;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PatientFormStatusUpdatedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $formId,
        public FormStatus $status,
        public FormType $formType = FormType::PrivateScan,
    ) {}
}
