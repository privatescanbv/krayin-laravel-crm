<?php

namespace App\Http\Controllers\Api;

use App\Enums\PatientMessageSenderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PatientMessagesIndexRequest;
use App\Http\Resources\PatientMessagesCollection;
use App\Models\PatientMessage;
use App\Services\Keycloak\KeycloakService;
use App\Services\patientmessages\PatientMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Webkul\Admin\Http\Resources\ActivityResource;

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
     *
     * @group Patient messages
     *
     * @urlParam id string required The Keycloak user ID of the patient. Example: 3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d
     *
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Items per page (max 100). Example: 15
     *
     * @response 200 scenario="Success" {"data":[{"id":1,"person_id":123,"sender_type":"patient","sender_id":null,"body":"Hallo","is_read":false,"created_at":"2026-02-01T10:00:00+01:00","updated_at":"2026-02-01T10:00:00+01:00","sender":null,"sender_name":"Patient"}],"meta":{"current_page":1,"per_page":15,"total":42}}
     * @response 200 scenario="Success (empty)" {"data":[],"meta":{"current_page":1,"per_page":15,"total":0}}
     * @response 404 scenario="Patient not found" {"message":"Not Found"}
     */
    public function index(PatientMessagesIndexRequest $request, string $keycloakUserId): JsonResponse
    {
        [$person, $user] = $this->keycloakService->resolvePersonOrUser($keycloakUserId);
        if (is_null($person)) {
            if (! is_null($user)) {
                $perPage = (int) $request->validated('per_page', 15);

                // handle as no messages for users
                return PatientMessagesCollection::empty($perPage)->response();
            }
            abort(404);
        }
        $personId = $person->id;
        logger()->info("Fetching patient messages for person ID: {$personId}");
        // Get all patient messages linked to this person
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);

        $paginator = PatientMessage::query()
            ->where('person_id', $personId)
            ->with(['sender', 'person'])
            ->orderBy('created_at', 'asc')
            ->paginate($perPage)
            ->appends($request->query());

        return PatientMessagesCollection::fromPaginator($paginator, $paginator->getCollection())->response();
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
     *
     * @group Patient messages
     *
     * @urlParam id string required The Keycloak user ID of the patient. Example: 3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d
     *
     * @response 200 scenario="Success" {"message":"Messages marked as read.","data":{"marked_count":3}}
     * @response 404 scenario="Patient not found" {"message":"Not Found"}
     */
    public function markAsRead(string $keycloakUserId): JsonResponse
    {
        [$person, $user] = $this->keycloakService->resolvePersonOrUser($keycloakUserId);
        if (is_null($person)) {
            logger()->error('No support for mark messages as read for users without person association.');
            abort(404);
        }

        $markedCount = $this->patientMessageService->markAllMessagesReadForPatient($person);

        return response()->json([
            'message' => 'Messages marked as read.',
            'data'    => [
                'marked_count' => $markedCount,
            ],
        ]);
    }
}
