<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PatientAppointmentsIndexRequest;
use App\Http\Resources\PatientAppointmentsCollection;
use App\Models\Clinic;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Services\Keycloak\KeycloakService;
use App\Services\OrderCheckService;
use Illuminate\Http\JsonResponse;

class PatientAppointmentController extends Controller
{
    public function __construct(
        private readonly KeycloakService $keycloakService,
        private readonly OrderCheckService $orderCheckService,
        private readonly OrderRepository $orderRepository,
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
     * @response 200 scenario="Success" {"data":[{"id":"order-123","patient_id":"1","practitioner_id":null,"clinic_id":null,"clinic_label":null,"clinic":{"id":1,"name":"Example Clinic","address":"Example street 1\n1234 AB Amsterdam"},"start_at":"2026-01-27T10:00:00+01:00","end_at":null,"timezone":"Europe/Amsterdam","is_remote":false,"remote_url":null,"created_at":"2026-01-20T09:00:00+01:00","updated_at":"2026-01-20T09:00:00+01:00"}],"meta":{"current_page":1,"per_page":15,"total":42}}
     * @response 200 scenario="Success (empty)" {"data":[],"meta":{"current_page":1,"per_page":15,"total":0}}
     * @response 404 scenario="Patient not found" {"message":"Not Found"}
     */
    public function index(PatientAppointmentsIndexRequest $request, string $keycloakUserId): JsonResponse
    {
        [$person, $user] = $this->keycloakService->resolvePersonOrUser($keycloakUserId);

        if (is_null($person)) {
            if (! is_null($user)) {
                $perPage = (int) $request->validated('per_page', 15);

                // No appointments for users without person association.
                return PatientAppointmentsCollection::empty($perPage)->response();
            }

            abort(404);
        }

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);
        $filter = strtolower((string) ($validated['filter'] ?? ''));
        $now = now();

        $paginator = $this->orderRepository
            ->paginatePatientAppointmentsForPerson($person, $perPage, $filter, $now)
            ->appends($request->query());

        $timezone = config('app.timezone') ?: 'Europe/Amsterdam';

        $appointments = $paginator->getCollection()->map(function (Order $order) use ($person, $timezone) {
            $firstAppointmentInClinic = $this->orderCheckService->retrieveClinicFromOrder($order, $person);
            $clinic = $firstAppointmentInClinic !== null ? Clinic::with('address')->find($firstAppointmentInClinic) : null;

            $clinicData = $clinic ? [
                'id'      => $clinic->id,
                'name'    => $clinic->name,
                'address' => $clinic->address?->multiline_address,
            ] : null;

            return [
                'id'              => 'order-'.$order->id,
                'patient_id'      => (string) $person->id,
                'practitioner_id' => null,
                'clinic_id'       => $clinic ? (string) $clinic->id : null,
                'clinic_label'    => $clinic ? $clinic->label() : null,
                'clinic'          => $clinicData,
                'start_at'        => $order->first_examination_at->toIso8601String(),
                'end_at'          => null,
                'timezone'        => $timezone,
                'is_remote'       => false,
                'remote_url'      => null,
                'created_at'      => $order->created_at->toIso8601String(),
                'updated_at'      => $order->updated_at?->toIso8601String(),
            ];
        });

        return PatientAppointmentsCollection::fromPaginator($paginator, $appointments)->response();
    }
}
