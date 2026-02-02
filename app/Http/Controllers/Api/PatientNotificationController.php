<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PatientNotificationsIndexRequest;
use App\Http\Resources\PatientNotificationsCollection;
use App\Services\Keycloak\KeycloakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
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
     */
    public function index(PatientNotificationsIndexRequest $request, string $keycloakUserId): JsonResponse
    {
        [$person, $user] = $this->keycloakService->resolvePersonOrUser($keycloakUserId);

        if (is_null($person)) {
            if (! is_null($user)) {
                $perPage = (int) $request->validated('per_page', 10);

                return PatientNotificationsCollection::empty($perPage)->response();
            }

            abort(404);
        }

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 10);
        $page = (int) ($validated['page'] ?? 1);

        // Temporary random data (no persistence yet)
        $total = 42;
        $offset = max(0, ($page - 1) * $perPage);

        $items = collect(range(1, $perPage))
            ->map(function (int $i) use ($offset) {
                $id = $offset + $i;

                return [
                    'id'          => $id,
                    'type'        => 'document',
                    'dismissable' => $id % 2 !== 0,
                    'title'       => 'Document update #'.$id,
                    'summary'     => ($id % 3 === 0) ? null : 'Er is een nieuw document beschikbaar.',
                    'reference'   => [
                        'type' => 'document',
                        'id'   => 1000 + $id,
                    ],
                    'created_at' => Carbon::now()->subMinutes($id)->toISOString(),
                    'read'       => $id % 4 === 0,
                ];
            });

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return PatientNotificationsCollection::fromPaginator($paginator, $paginator->getCollection())->response();
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

        // Temporary rule for random data: odd ids are dismissable
        $dismissable = $notificationId % 2 !== 0;

        if (! $dismissable) {
            return response()->json([
                'message' => 'Only dismissable notifications can be marked as read.',
            ], 422);
        }

        return response()->json(null, 204);
    }
}
