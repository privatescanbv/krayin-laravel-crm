<?php

namespace App\Events;

use App\Enums\FormType;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Webkul\Contact\Models\Person;

class PatientFormCompletedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Person $person,
        public string $formId,
        public FormType $formType = FormType::PrivateScan,
    ) {}
}
