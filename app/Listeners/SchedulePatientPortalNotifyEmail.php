<?php

namespace App\Listeners;

use App\Enums\PersonPreferenceKey;
use App\Events\PatientNotifyEvent;
use App\Models\PatientNotification;
use App\Models\PersonPreference;
use Illuminate\Support\Facades\Log;
use Throwable;
use Webkul\Contact\Models\Person;

class SchedulePatientPortalNotifyEmail
{
    public function handle(PatientNotifyEvent $event): void
    {
        try {
            $person = Person::query()->find($event->patientId);

            if (! $person) {
                return;
            }

            if (! $person->findDefaultEmail()) {
                return;
            }

            if (! PersonPreference::getValueForPerson($person->id, PersonPreferenceKey::EMAIL_NOTIFICATIONS_ENABLED)) {
                return;
            }

            if (! PatientNotification::forPatient($event->patientId)->forMailNotification()->exists()) {
                return;
            }

            if (
                $person->patient_portal_notify_scheduled_at
                && $person->patient_portal_notify_scheduled_at->isFuture()
            ) {
                return;
            }

            $interval = max(1, (int) config('services.portal.patient.notify_email_interval_minutes', 120));
            $now = now();

            $last = $person->patient_portal_last_notify_email_at;

            if ($last) {
                $earliest = $last->copy()->addMinutes($interval);
                if ($now->lt($earliest)) {
                    $scheduledAt = $earliest;
                } else {
                    $scheduledAt = $now->copy()->addMinutes($interval);
                }
            } else {
                $scheduledAt = $now->copy()->addMinutes($interval);
            }

            $person->forceFill([
                'patient_portal_notify_scheduled_at' => $scheduledAt,
            ])->save();
        } catch (Throwable $e) {
            Log::error('SchedulePatientPortalNotifyEmail failed', [
                'patient_id' => $event->patientId,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
