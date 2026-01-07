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
        $activity = null;

        // 1. Check if activity is already linked to the message
        if ($patientMessage->activity_id) {
            $activity = $patientMessage->activity;
        }
        // 2. If not, look for an existing activity for this person
        elseif ($patientMessage->person_id) {
            $activity = Activity::where('type', ActivityType::PATIENT_MESSAGE->value)
                ->whereHas('persons', function ($query) use ($patientMessage) {
                    $query->where('persons.id', $patientMessage->person_id);
                })
                ->latest()
                ->first();
        }

        // 3. If activity found, reuse and reopen if necessary
        if ($activity) {
            if ($activity->is_done) {
                logger()->info('Reopen activity, new patient message', ['person_id'=>$patientMessage->person_id]);
                // Reopen the existing activity instead of creating a new one
                $activity->reopen()->save();
            }

            // Ensure the message is linked to this activity
            if ($patientMessage->activity_id !== $activity->id) {
                PatientMessage::withoutEvents(function () use ($patientMessage, $activity) {
                    $patientMessage->update(['activity_id' => $activity->id]);
                });
            }

            return;
        }

        logger()->info('Create activity for patient message', ['person_id' => $patientMessage->person_id, 'type' => $patientMessage->sender_type->value]);
        // 4. Create new activity if none exists
        $activity = Activity::create([
            'type'          => ActivityType::PATIENT_MESSAGE->value,
            'title'         => 'Berichten patiënt portaal', // Or staff/system based on sender_type
            //            'comment'       => $patientMessage->body,
            'user_id'       => $patientMessage->sender_id, // Assigned to sender if staff, or null/default logic if patient
            'is_done'       => 0,
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
