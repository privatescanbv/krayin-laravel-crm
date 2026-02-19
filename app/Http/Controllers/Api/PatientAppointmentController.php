<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityType;
use App\Enums\AppointmentTimeFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PatientAppointmentsIndexRequest;
use App\Http\Resources\PatientPortalAppointmentResource;
use App\Repositories\ActivityRepository;
use App\Repositories\OrderRepository;
use App\Services\Keycloak\KeycloakService;
use App\Services\OrderCheckService;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Webkul\Contact\Models\Person;

class PatientAppointmentController extends Controller
{
    /**
     * Activity types that are exposed as appointments in the patient portal.
     * Extend this list to include additional activity types when needed.
     *
     * @var ActivityType[]
     */
    public const PORTAL_ACTIVITY_TYPES = [
        ActivityType::MEETING,
    ];

    public function __construct(
        private readonly KeycloakService $keycloakService,
        private readonly OrderRepository $orderRepository,
        private readonly ActivityRepository $activityRepository,
    ) {}

    /**
     * Get appointments for a patient (derived from Orders and published Activities).
     *
     * @group Patient appointments
     *
     * @urlParam id string required The Keycloak user ID of the patient. Example: 3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d
     *
     * @queryParam filter string Filter appointments. Allowed values: future, past. Example: future
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Items per page (max 100). Example: 15
     *
     * @response 200 scenario="Success" {"data":[{"id":"order-123","patient_id":"1","practitioner_id":null,"clinic_id":null,"clinic_ref":null,"start_at":"2026-01-27T10:00:00+01:00","end_at":null,"timezone":"Europe/Amsterdam","is_remote":false,"remote_url":null,"created_at":"2026-01-20T09:00:00+01:00","updated_at":"2026-01-20T09:00:00+01:00"}],"meta":{"current_page":1,"per_page":15,"total":42}}
     * @response 200 scenario="Success (empty)" {"data":[],"meta":{"current_page":1,"per_page":15,"total":0}}
     * @response 404 scenario="Patient not found" {"message":"Not Found"}
     */
    public function index(PatientAppointmentsIndexRequest $request, string $keycloakUserId): AnonymousResourceCollection
    {
        [$person, $user] = $this->keycloakService->resolvePersonOrUser($keycloakUserId);

        if (is_null($person)) {
            if (! is_null($user)) {
                return PatientPortalAppointmentResource::collection(collect());
            }

            abort(404);
        }

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);
        $filter = $request->timeFilter();
        $page = (int) ($request->query('page', 1));
        $now = now();

        $items = $this->collectOrderItems($person, $filter, $now)
            ->concat($this->collectActivityItems($person, $filter, $now))
            ->sortBy('sort_at')
            ->values();

        $paginator = new LengthAwarePaginator(
            $items->forPage($page, $perPage),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return PatientPortalAppointmentResource::collection($paginator);
    }

    /**
     * Collect order-based appointment items for a person.
     */
    private function collectOrderItems(Person $person, ?AppointmentTimeFilter $filter, Carbon $now): Collection
    {
        return $this->orderRepository
            ->queryPatientAppointmentsForPerson($person, $filter, $now)
            ->get()
            ->map(fn ($order) => [
                'source'  => 'order',
                'sort_at' => $order->first_examination_at,
                'payload' => [
                    'order'  => $order,
                    'clinic' => app(OrderCheckService::class)->retrieveClinicFromOrder($order, $person),
                    'person' => $person,
                ],
            ]);
    }

    /**
     * Collect activity-based appointment items for a person.
     * Fetches each configured portal activity type.
     */
    private function collectActivityItems(Person $person, ?AppointmentTimeFilter $filter, Carbon $now): Collection
    {
        $items = collect();

        foreach (self::PORTAL_ACTIVITY_TYPES as $type) {
            $activities = $this->activityRepository
                ->queryPatientActivitiesForPerson($person, $type, $filter, $now)
                ->get()
                ->map(fn ($activity) => [
                    'source'  => 'activity',
                    'sort_at' => $activity->schedule_from,
                    'payload' => [
                        'activity' => $activity,
                        'clinic'   => $activity->clinic_id,
                        'person'   => $person,
                    ],
                ]);

            $items = $items->concat($activities);
        }

        return $items;
    }
}
