<?php

namespace App\Http\Controllers\Api;

use App\Enums\PatientMessageSenderType;
use App\Http\Controllers\Controller;
use App\Http\Resources\PatientMessageResource;
use App\Models\PatientMessage;
use App\Services\Keycloak\KeycloakService;
use App\Services\patientmessages\PatientMessageService;
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
    public function __construct(
        private readonly KeycloakService $keycloakService,
        private readonly PatientMessageService $patientMessageService,
    ) {}

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
            ->orderBy('created_at', 'asc')
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
        logger()->info('request add post = '.print_r($request->all(), true));
        $validated = $request->validate([
            'body'   => 'required|string',
        ]);

        $patientMessage = DB::transaction(function () use ($validated, $person) {
            $message = PatientMessage::create([
                'sender_type'          => PatientMessageSenderType::PATIENT->value,
                'body'                 => $validated['body'],
                'person_id'            => $person->id,
            ]);

            //            // Link to person
            //            $message->persons()->attach($person->id);
            //            // required to get the event in ActvityObserver#update
            //            $activity->touch();

            return $message;
        });

        // Refresh to get activity_id set by observer
        $patientMessage->refresh();

        return response()->json([
            'message' => 'Message created successfully.',
            'data'    => new ActivityResource($patientMessage->activity->load('user', 'persons')),
        ], 201);
    }

    /**
     * Mark all messages as read by patient (not employee)
     */
    public function markAsRead(string $keycloakUserId)
    {
        [$person, $user] = $this->keycloakService->resolvePersonOrUser($keycloakUserId);
        if (is_null($person)) {
            logger()->error('No support for mark messages as read for users without person association.');
            abort(404);
        }
        $this->patientMessageService->markAllMessagesReadForPatient($person);
    }
}
