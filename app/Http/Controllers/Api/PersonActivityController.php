<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Models\Activity;
use Webkul\Admin\Http\Resources\ActivityResource;
use Webkul\Contact\Models\Person;

/**
 * Controller for managing person activities, specifically patient messages.
 */
class PersonActivityController extends Controller
{
    /**
     * Get all patient messages for a person, grouped by thread.
     */
    public function index(string $keycloakUserId): JsonResponse
    {
        $person = Person::where('keycloak_user_id', $keycloakUserId)->first();
        if (is_null($person)) {
            logger()->debug('Person not found for Keycloak User ID: '.$keycloakUserId);
            abort(404);
        }
        $personId = $person->id;
        logger()->info("Fetching patient messages for person ID: {$personId}");
        // Get all activities of type PATIENT_MESSAGE linked to this person
        $activities = Activity::query()
            ->where('type', ActivityType::PATIENT_MESSAGE->value)
            ->whereHas('persons', function ($query) use ($personId) {
                $query->where('persons.id', $personId);
            })
            ->with(['user', 'persons'])
            ->orderBy('created_at', 'desc') // Newest threads first
            ->get();

        return response()->json([
            'data' => ActivityResource::collection($activities),
        ]);
    }

    /**
     * Store a new patient message or reply.
     */
    public function store(Request $request, string $keycloakUserId): JsonResponse
    {
        $person = Person::where('keycloak_user_id', $keycloakUserId)->first();
        if (is_null($person)) {
            logger()->debug('Person not found for Keycloak User ID: '.$keycloakUserId);
            abort(404);
        }
        $personId = $person->id;
        $person = Person::findOrFail($personId);

        $validated = $request->validate([
            'title'     => 'nullable|string|max:255',
            'comment'   => 'required|string',
        ]);

        $activity = DB::transaction(function () use ($validated, $person) {
            $activity = Activity::create([
                'type'          => ActivityType::PATIENT_MESSAGE->value,
                'title'         => $validated['title'] ?? 'New Message',
                'comment'       => $validated['comment'],
                'user_id'       => auth()->id(), // Null if not authenticated (e.g. public API? but routes are protected)
                'is_done'       => 1, // Messages are instant
                'location'      => null,
                'schedule_from' => now(),
                'schedule_to'   => now(),
            ]);

            // Link to person
            $activity->persons()->attach($person->id);
            // required to get the event in ActvityObserver#update
            $activity->touch();

            return $activity;
        });

        return response()->json([
            'message' => 'Message created successfully.',
            'data'    => new ActivityResource($activity->load('user', 'persons')),
        ], 201);
    }

    public function markAsRead(string $id, string $messageId) {}
}
