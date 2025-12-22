<?php

namespace App\Http\Controllers\Api;

use App\Enums\PatientMessageSenderType;
use App\Http\Controllers\Controller;
use App\Models\PatientMessage;
use Illuminate\Http\JsonResponse;
use Webkul\Contact\Models\Person;

class PatientMessageController extends Controller
{
    /**
     * Get the count of unread messages for a specific person.
     */
    public function unreadCount(string $keycloakUserId): JsonResponse
    {
        $person = Person::where('keycloak_user_id', $keycloakUserId)->firstOrFail();

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
}
