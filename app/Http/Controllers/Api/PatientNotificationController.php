<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PatientNotificationsIndexRequest;
use App\Http\Resources\PatientNotificationResource;
use App\Models\PatientNotification;
use App\Services\Keycloak\KeycloakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class PatientNotificationController extends Controller
{
    public function __construct(private readonly KeycloakService $keycloakService) {}

    /**
     * Get notifications for a patient.
     *
     * @group Patient notifications
     *
     * @urlParam id string required The Keycloak user ID of the patient. Example: 3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d
     *
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Items per page (max 10). Example: 10
     *
     * @responseField notifications object[] List of notifications.
     * @responseField notifications[].id integer The notification ID.
     * @responseField notifications[].dismissable boolean Whether the notification can be dismissed.
     * @responseField notifications[].title string The notification title.
     * @responseField notifications[].summary string The notification summary.
     * @responseField notifications[].reference object Reference to related resource.
     * @responseField notifications[].reference.type string The reference type (activity, gvl_form).
     * @responseField notifications[].reference.id integer The referenced resource ID.
     * @responseField notifications[].reference.url string Optional URL to the referenced resource.
     * @responseField notifications[].created_at string ISO 8601 timestamp.
     * @responseField meta object Pagination metadata.
     * @responseField meta.current_page integer Current page number.
     * @responseField meta.per_page integer Items per page.
     * @responseField meta.total integer Total number of notifications.
     */
    public function index(PatientNotificationsIndexRequest $request, string $keycloakUserId): AnonymousResourceCollection
    {
        [$person, $user] = $this->keycloakService->resolvePersonOrUser($keycloakUserId);

        if (is_null($person)) {
            if (! is_null($user)) {
                return PatientNotificationResource::collection(collect());
            }

            abort(404);
        }

        $locale = $person->preferred_language?->value ?? 'nl';
        app('request')->attributes->set('patient_locale', $locale);

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 10);

        $now = Carbon::now();

        $paginator = PatientNotification::forPatient($person->id)
            ->whereNull('dismissed_at')
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now);
            })
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->appends($request->query());

        // Update read_at when we "send" the notifications in this response.
        $unreadIds = $paginator->getCollection()
            ->filter(fn (PatientNotification $n) => is_null($n->read_at))
            ->pluck('id')
            ->values();

        if ($unreadIds->isNotEmpty()) {
            PatientNotification::forPatient($person->id)
                ->whereIn('id', $unreadIds->all())
                ->update([
                    'read_at'    => $now,
                ]);

            $paginator->getCollection()->each(function (PatientNotification $n) use ($now) {
                if (is_null($n->read_at)) {
                    $n->read_at = $now;
                }
            });
        }

        return PatientNotificationResource::collection($paginator);
    }

    /**
     * Mark a dismissable notification as read.
     *
     * @group Patient notifications
     *
     * @urlParam id string required The Keycloak user ID of the patient. Example: 3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d
     * @urlParam notificationId integer required The notification id. Example: 123
     *
     * @response 204 scenario="Success" {}
     * @response 404 scenario="Patient not found" {"message":"Not Found"}
     * @response 422 scenario="Not dismissable" {"message":"Only dismissable notifications can be marked as read."}
     */
    public function markAsRead(string $keycloakUserId, int $notificationId): JsonResponse
    {
        [$person, $user] = $this->keycloakService->resolvePersonOrUser($keycloakUserId);

        if (is_null($person) && is_null($user)) {
            abort(404);
        }

        if (is_null($person)) {
            abort(404);
        }

        /** @var PatientNotification $notification */
        $notification = PatientNotification::query()
            ->where('patient_id', (int) $person->id)
            ->findOrFail($notificationId);

        if (! $notification->dismissable) {
            return response()->json([
                'message' => 'Only dismissable notifications can be marked as read.',
            ], 422);
        }

        $now = Carbon::now();

        // Use dismissed_at to mark as "read/dismissed".
        $notification->dismissed_at = $now;
        if (is_null($notification->read_at)) {
            $notification->read_at = $now;
        }
        $notification->save();

        return response()->json(null, 204);
    }
}
