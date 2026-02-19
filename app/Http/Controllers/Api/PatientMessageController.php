<?php

namespace App\Http\Controllers\Api;

use App\Enums\PatientMessageSenderType;
use App\Http\Controllers\Controller;
use App\Models\PatientMessage;
use Illuminate\Http\RedirectResponse;
use Webkul\Activity\Models\Activity;

class PatientMessageController extends Controller
{
    /**
     * Get the count of unread messages for a specific person.
     */
    public function store(): RedirectResponse
    {
        request()->merge([
            'is_read' => filter_var(
                request()->input('is_read', true),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ) ?? true,
        ]);
        request()->validate([
            'body'        => 'required',
            'activity_id' => 'required|exists:activities,id',
            'is_read'     => 'boolean',
        ]);

        $activity = Activity::findOrFail(request('activity_id'));

        $person = $activity->getPatientFromActivity();

        if (! $person) {
            abort(404, 'No person found associated with this activity.');
        }

        PatientMessage::create([
            'person_id'   => $person->id,
            'sender_type' => PatientMessageSenderType::STAFF,
            'sender_id'   => auth()->id(),
            'body'        => request('body'),
            'activity_id' => $activity->id,
        ]);

        return redirect()->route('admin.activities.view', $activity->id);
    }
}
