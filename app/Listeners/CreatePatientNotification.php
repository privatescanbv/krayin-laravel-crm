<?php

namespace App\Listeners;

use App\Events\PatientNotifyEvent;
use App\Models\PatientNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreatePatientNotification
{
    public function handle(PatientNotifyEvent $event): void
    {
        try {
            Log::info('Creating patient notification', [
                'patient_id'     => $event->patientId,
                'reference_type' => $event->referenceType,
                'reference_id'   => $event->referenceId,
            ]);
            PatientNotification::create([
                'patient_id'     => $event->patientId,
                'dismissable'    => $event->dismissable,
                'title'          => $event->title,
                'summary'        => $event->summary,
                'reference_type' => $event->referenceType,
                'reference_id'   => $event->referenceId,
                'created_by'     => $event->userId,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to create patient notification', [
                'patient_id'     => $event->patientId,
                'reference_type' => $event->referenceType,
                'reference_id'   => $event->referenceId,
                'error'          => $e->getMessage(),
            ]);
        }
    }
}
