<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PatientAppointmentsIndexRequest;
use App\Http\Resources\AppointmentDetailResource;
use App\Http\Resources\PatientPortalAppointmentResource;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Services\AppointmentDetailService;
use App\Services\Keycloak\KeycloakService;
use App\Services\OrderCheckService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Webkul\Contact\Models\Person;

class PatientAppointmentController extends Controller
{
    public function __construct(
        private readonly KeycloakService $keycloakService,
        private readonly OrderRepository $orderRepository,
        private readonly OrderCheckService $orderCheckService,
        private readonly AppointmentDetailService $appointmentDetailService,
    ) {}

    /**
     * Get appointments for a patient (derived from Orders).
     *
     * @group Patient appointments
     *
     * @urlParam id string required The Keycloak user ID of the patient. Example: 3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d
     *
     * @queryParam filter string Filter appointments. Allowed values: future, past. Example: future
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Items per page (max 100). Example: 15
     *
     * @response 200 scenario="Success" {"data":[{"id":"42","patient_id":"1","clinic_id":null,"clinic_ref":null,"start_at":"2026-01-27T10:00:00+01:00","end_at":"2026-01-27T10:30:00+01:00","timezone":"Europe/Amsterdam","created_at":"2026-01-20T09:00:00+01:00","updated_at":"2026-01-20T09:00:00+01:00"}],"meta":{"current_page":1,"per_page":15,"total":42}}
     * @response 200 scenario="Success (empty)" {"data":[],"meta":{"current_page":1,"per_page":15,"total":0}}
     * @response 403 scenario="Caller is not a patient (e.g. a clinic-portal account)" {"message":"Forbidden"}
     * @response 404 scenario="Patient not found" {"message":"Not Found"}
     */
    public function index(PatientAppointmentsIndexRequest $request, string $keycloakUserId): AnonymousResourceCollection
    {
        $person = $this->keycloakService->resolvePatientOrFail($keycloakUserId);

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);
        $filter = $request->timeFilter();
        $page = (int) ($request->query('page', 1));
        $now = now();

        $orders = $this->orderRepository
            ->getPatientAppointmentOrdersForPerson($person, $filter, $now)
            ->sortBy(fn (Order $order) => $order->firstExaminationCarbon())
            ->values();

        // Resolve booking/clinic only for the requested page (1-2 queries per order).
        $paginator = new LengthAwarePaginator(
            $orders->forPage($page, $perPage)->map(fn (Order $order) => $this->appointmentPayload($order, $person))->values(),
            $orders->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return PatientPortalAppointmentResource::collection($paginator);
    }

    /**
     * Detail of a single appointment (tickets/portaal/02-appointment-detail.md). The id is the
     * booking (`ResourceOrderItem`) id; the legacy `order-{id}` form is still accepted.
     *
     * @group Patient appointments
     *
     * @urlParam id string required The Keycloak user ID of the patient.
     * @urlParam appointmentId string required The appointment id, as returned by the list endpoint.
     *
     * @response 403 scenario="Caller is not a patient (e.g. a clinic-portal account)" {"message":"Forbidden"}
     * @response 404 scenario="Unknown patient, or appointment of another patient"
     */
    public function show(string $keycloakUserId, string $appointmentId): AppointmentDetailResource
    {
        $person = $this->keycloakService->resolvePatientOrFail($keycloakUserId);

        [$order, $booking] = $this->appointmentDetailService->resolve(
            $appointmentId,
            fn ($q) => $q->forPerson($person),
            $person,
        );

        return new AppointmentDetailResource($this->appointmentDetailService->build($order, $booking, $person));
    }

    /**
     * Payload for {@see PatientPortalAppointmentResource}.
     *
     * @return array<string, mixed>
     */
    private function appointmentPayload(Order $order, Person $person): array
    {
        $booking = $this->orderCheckService->retrieveFirstBookingForOrder($order, $person);
        $department = $booking?->resource?->clinicDepartment;

        return [
            'order'      => $order,
            'booking'    => $booking,
            'clinic'     => $booking?->resource?->clinic,
            'department' => $department,
            'person'     => $person,
        ];
    }
}
