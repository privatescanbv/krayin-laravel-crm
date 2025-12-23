<?php

namespace App\Http\Controllers\Api;

use App\Enums\PatientMessageSenderType;
use App\Http\Controllers\Controller;
use App\Models\PatientMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;

class PatientMessageController extends Controller
{
    /**
     * Get the count of unread messages for a specific person.
     */
    public function unreadCount(string $keycloakUserId): JsonResponse
    {
        [$person, $user] = Person::where('keycloak_user_id', $keycloakUserId)->firstOrFail();
        if (! is_null($user)) {
            return response()->json([
                'message' => 'No messages for users without person association.',
                'data'    => [
                    'new_messages_count'     => 0,
                    'new_appointments_count' => 0,
                    'new_docs_count'         => 0,
                ],
            ], 200);
        }

        // Count unread messages sent by STAFF or SYSTEM
        $messageCount = PatientMessage::where('person_id', $person->id)
            ->whereIn('sender_type', [PatientMessageSenderType::STAFF, PatientMessageSenderType::SYSTEM])
            ->where('is_read', false)
            ->count();

        return response()->json([
            'message' => 'Counts retrieved successfully.',
            'data'    => [
                'new_messages_count'     => $messageCount,
                'new_appointments_count' => 0,
                'new_docs_count'         => 0,
            ],
        ], 200);
    }

    public function store(): RedirectResponse
    {
        request()->merge([
            'is_read' => filter_var(
                request()->input('is_read', true),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ) ?? true,
        ]);
        request()->validate(request(), [
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
            'is_read'     => request('is_read', true),
        ]);

        return redirect()->route('admin.activities.view', $activity->id);
    }
}
