<?php

namespace App\Listeners;

use App\Enums\NotificationReferenceType;
use App\Events\PatientNotifyEvent;
use App\Models\PatientNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreatePatientNotification
{
    private const FILE_TITLE_KEY = 'patient_notifications.file.title';

    private const FILE_SUMMARY_KEY = 'patient_notifications.file.summary';

    private const GVL_TITLE_KEY = 'patient_notifications.gvl.title';

    private const GVL_SUMMARY_KEY = 'patient_notifications.gvl.summary';

    public function handle(PatientNotifyEvent $event): void
    {
        try {
            Log::info('Creating patient notification', [
                'patient_id'     => $event->patientId,
                'reference_type' => $event->referenceType,
                'reference_id'   => $event->referenceId,
            ]);

            if ($event->referenceType === NotificationReferenceType::FILE) {
                $existing = PatientNotification::query()
                    ->where('patient_id', $event->patientId)
                    ->where('reference_type', NotificationReferenceType::FILE)
                    ->whereNull('dismissed_at')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    })
                    ->first();

                if ($existing) {
                    $existing->update([
                        'entity_names' => array_merge($existing->entity_names ?? [], [$event->entityName]),
                    ]);

                    return;
                }

                PatientNotification::create([
                    'patient_id'     => $event->patientId,
                    'dismissable'    => $event->dismissable,
                    'title'          => self::FILE_TITLE_KEY,
                    'summary'        => self::FILE_SUMMARY_KEY,
                    'entity_names'   => [$event->entityName],
                    'reference_type' => $event->referenceType,
                    'reference_id'   => $event->referenceId,
                    'created_by'     => $event->userId,
                ]);

                return;
            } elseif ($event->referenceType === NotificationReferenceType::GVL_FORM) {
                $existing = PatientNotification::query()
                    ->where('patient_id', $event->patientId)
                    ->where('reference_type', NotificationReferenceType::GVL_FORM)
                    ->whereNull('dismissed_at')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    })
                    ->first();

                if ($existing) {
                    $existing->update([
                        'reference_id' => $event->referenceId,
                        'entity_names' => array_merge($existing->entity_names ?? [], [$event->entityName]),
                    ]);

                    return;
                }

                PatientNotification::create([
                    'patient_id'     => $event->patientId,
                    'dismissable'    => $event->dismissable,
                    'title'          => self::GVL_TITLE_KEY,
                    'summary'        => self::GVL_SUMMARY_KEY,
                    'entity_names'   => [$event->entityName],
                    'reference_type' => $event->referenceType,
                    'reference_id'   => $event->referenceId,
                    'created_by'     => $event->userId,
                ]);

                return;
            } else {
                Log::warning('Unknown notification reference type', [
                    'reference_type' => $event->referenceType,
                ]);
            }
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
