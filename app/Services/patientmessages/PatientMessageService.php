<?php

namespace App\Services\patientmessages;

use App\Enums\PatientMessageSenderType;
use App\Models\PatientMessage;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;

class PatientMessageService
{
    /**
     * Called from employee
     */
    public function markAllMessagesAsReadForEmployee(Activity $activity): void
    {
        PatientMessage::query()
            ->where('activity_id', $activity->id)
            ->where('sender_type', PatientMessageSenderType::PATIENT)
            ->update(['is_read' => true]);
    }

    /**
     * Called from patient
     */
    public function markAllMessagesReadForPatient(Person $person): void
    {
        PatientMessage::query()
            ->where('person_id', $person->id)
            ->whereNot('sender_type', PatientMessageSenderType::PATIENT)
            ->update(['is_read' => true]);
    }
}
