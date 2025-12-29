<?php

namespace App\Observers;

use App\Enums\ActivityType;
use App\Models\PatientMessage;
use Webkul\Activity\Models\Activity;

class PatientMessageObserver
{
    /**
     * Handle the PatientMessage "created" event.
     */
    public function created(PatientMessage $patientMessage): void
    {
        // Don't create an activity if one is already linked (e.g. manually set)
        if ($patientMessage->activity_id && $patientMessage->activity()->is_done) {
            $activity = $patientMessage->activity();
            /**
             * @var $activity Activity
             */
            $activity = $activity->reOpen();
            $activity->save();

            return;
        }

        // Create the activity
        $activity = Activity::create([
            'type'          => ActivityType::PATIENT_MESSAGE->value,
            'title'         => 'Nieuw bericht van patiënt', // Or staff/system based on sender_type
            'comment'       => $patientMessage->body,
            'user_id'       => $patientMessage->sender_id, // Assigned to sender if staff, or null/default logic if patient
            'is_done'       => 1, // Messages are typically history, not tasks
            'location'      => null,
            'schedule_from' => $patientMessage->created_at,
            'schedule_to'   => $patientMessage->created_at,
        ]);

        // Link the activity to the person
        if ($patientMessage->person_id) {
            $activity->persons()->attach($patientMessage->person_id);
        }

        // Update the message with the activity ID (without triggering observer again)
        PatientMessage::withoutEvents(function () use ($patientMessage, $activity) {
            $patientMessage->update(['activity_id' => $activity->id]);
        });
    }

    /**
     * Handle the PatientMessage "updated" event.
     */
    public function updated(PatientMessage $patientMessage): void
    {
        // Sync activity comment if body changes?
    }

    /**
     * Handle the PatientMessage "deleted" event.
     */
    public function deleted(PatientMessage $patientMessage): void
    {
        //
    }

    /**
     * Handle the PatientMessage "restored" event.
     */
    public function restored(PatientMessage $patientMessage): void
    {
        //
    }

    /**
     * Handle the PatientMessage "force deleted" event.
     */
    public function forceDeleted(PatientMessage $patientMessage): void
    {
        //
    }
}
