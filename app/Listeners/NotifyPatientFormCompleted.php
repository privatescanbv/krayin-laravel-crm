<?php

namespace App\Listeners;

use App\Enums\NotificationReferenceType;
use App\Events\PatientFormCompletedEvent;
use App\Models\PatientNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotifyPatientFormCompleted
{
    public function handle(PatientFormCompletedEvent $event): void
    {
        try {
            PatientNotification::where('patient_id', $event->person->id)
                ->whereIn('reference_type', [
                    NotificationReferenceType::GVL_FORM,
                    NotificationReferenceType::DIAGNOSIS_FORM,
                ])
                ->where('reference_id', $event->formId)
                ->delete();
        } catch (Throwable $e) {
            Log::error('Failed to delete form patient notification on form completed', [
                'person_id' => $event->person->id,
                'form_id'   => $event->formId,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
