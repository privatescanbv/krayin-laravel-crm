<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Resources\PatientMessageResource;
use App\Models\PatientMessage;
use App\Services\Keycloak\KeycloakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Webkul\Admin\Http\Resources\ActivityResource;
use Webkul\Contact\Models\Person;

/**
 * Controller for managing person activities, specifically patient messages.
 */
class PersonActivityController extends Controller
{
    public function __construct(private KeycloakService $keycloakService) {}

    /**
     * Get all patient messages for a person, grouped by thread.
     */
    public function index(string $keycloakUserId): JsonResponse
    {
        [$person, $user] = $this->keycloakService->resolvePersonOrUser($keycloakUserId);
        if (is_null($person)) {
            if (! is_null($user)) {
                // handle as no messages for users
                return response()->json([
                    'data' => [],
                ]);
            }
            abort(404);
        }
        $personId = $person->id;
        logger()->info("Fetching patient messages for person ID: {$personId}");
        // Get all patient messages linked to this person
        $messages = PatientMessage::query()
            ->where('person_id', $personId)
            ->with(['sender'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => PatientMessageResource::collection($messages),
        ]);
    }

    /**
     * Store a new patient message or reply.
     */
    public function store(Request $request, string $keycloakUserId): JsonResponse
    {
        [$person, $user] = $this->keycloakService->resolvePersonOrUser($keycloakUserId);
        if (is_null($person)) {
            if (! is_null($user)) {
                logger()->error('No support for creating messages for users without person association.');

                return response()->json([
                    'data' => [],
                ]);
            }
            abort(404);
        }
        $personId = $person->id;
        $person = Person::findOrFail($personId);

        $validated = $request->validate([
            'title'     => 'nullable|string|max:255',
            'comment'   => 'required|string',
        ]);

        $activity = DB::transaction(function () use ($validated, $person) {
            $message = PatientMessage::create([
                'sender_type'          => ActivityType::PATIENT_MESSAGE->value,
                'body'                 => ($validated['title'] ?? 'New Message').' '.$validated['comment'],
                'person_id'            => $person->id,
            ]);

            //            // Link to person
            //            $message->persons()->attach($person->id);
            //            // required to get the event in ActvityObserver#update
            //            $activity->touch();

            return $message;
        });

        return response()->json([
            'message' => 'Message created successfully.',
            'data'    => new ActivityResource($activity->load('user', 'persons')),
        ], 201);
    }

    public function markAsRead(string $id, string $messageId)
    {
        logger()->warning("please implement me. Marking message as read: {$messageId}");
    }
}
