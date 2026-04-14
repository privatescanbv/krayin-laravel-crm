<?php

namespace App\Actions\Activities;

use App\Enums\ActivityType;
use App\Enums\NotificationReferenceType;
use App\Enums\PatientMessageSenderType;
use App\Events\PatientNotifyEvent;
use App\Models\PatientMessage;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Models\File as ActivityFile;
use Webkul\Contact\Models\Person;

class CreatePatientMessageFromActivityAction
{
    /**
     * Notify portal persons after an activity has been published to the portal.
     * Call this after syncing the activity_portal_persons pivot.
     */
    public static function notifyPortalPersons(Activity $activity, ?int $userId = null): void
    {
        if ($activity->type != ActivityType::FILE) {
            return;
        }

        $patients = $activity->portalPersons;

        if ($patients->isEmpty()) {
            return;
        }

        $entityName = ActivityFile::query()->where('activity_id', $activity->id)->value('name') ?? $activity->title;

        foreach ($patients as $patient) {
            logger()->info("CreatePatientMessageFromActivityAction: Notifying Person ID: {$patient->id} for Activity ID: {$activity->id}");
            PatientNotifyEvent::dispatch(
                $patient->id,
                $entityName,
                NotificationReferenceType::FILE,
                $activity->id,
                true,
                $userId ?? auth()->id()
            );
        }
    }

    public function handle(Activity $activity, string $action, ?Person $person = null): void
    {
        if ((($activity->additional ?? [])['skip_patient_message_creation'] ?? false)) {
            return;
        }

        if ($activity->type == ActivityType::PATIENT_MESSAGE) {

            // Check if there is already a linked PatientMessage (to avoid infinite loops with PatientMessageObserver)
            if ($activity->patientMessages()->exists()) {
                return;
            }

            // Determine person_id
            $personId = null;

            if (! is_null($person)) {
                $personId = $person->id;
            } elseif ($activity->person_id) {
                $personId = $activity->person_id;
            }

            // Attempt 2: Check via Lead
            if (! $personId && $activity->lead_id) {
                $lead = $activity->lead;
                if ($lead) {
                    // Try 'contact_person_id' first
                    if ($lead->contact_person_id) {
                        $personId = $lead->contact_person_id;
                    }
                    $firstPersonOfLead = $lead->person()->first();
                    if ($firstPersonOfLead) {
                        $personId = $firstPersonOfLead->id;
                    } elseif ($lead->persons()->count() > 0) {
                        $personId = $lead->persons()->first()->id;
                    }
                }
            }

            if ($personId) {
                logger()->info("CreatePatientMessageFromActivityAction #$action: Creating PatientMessage for Activity ID: ".$activity->id.' linked to Person ID: '.$personId);
                PatientMessage::create([
                    'person_id'   => $personId,
                    'sender_type' => PatientMessageSenderType::STAFF, // Created by staff (Activity created by user)
                    'sender_id'   => $activity->user_id, // The staff member
                    'body'        => $activity->title."\n\n".$activity->comment,
                    'activity_id' => $activity->id,
                ]);
            } else {
                logger()->warning("CreatePatientMessageFromActivityAction #$action: Unable to determine person_id for Activity ID: ".$activity->id);
                // We only log debug here to avoid spamming error logs during the 'created' phase if 'updated' will fix it later.
                // But if it's the updated phase and still no person, that's an issue.
                // Since we call this on created AND updated, silence is safer unless we are sure it's final.
                // logger()->debug('ActivityObserver: No person_id found yet for Activity ID: ' . $activity->id);
            }
        }
    }
}
