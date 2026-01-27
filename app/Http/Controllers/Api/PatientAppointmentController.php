<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Clinic;
use App\Models\Order;
use App\Services\Keycloak\KeycloakService;
use App\Services\OrderCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientAppointmentController extends Controller
{
    public function __construct(
        private readonly KeycloakService $keycloakService,
        private readonly OrderCheckService $orderCheckService,
    ) {}

    /**
     * Get appointments for a patient (derived from Orders).
     *
     * @group Patient appointments
     *
     * @urlParam id string required The Keycloak user ID of the patient. Example: 3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d
     *
     * @queryParam filter string Filter appointments. Allowed values: future, past. Example: future
     *
     * @response 200 scenario="Success" {"data":[{"id":"order-123","patient_id":"1","practitioner_id":null,"clinic_id":null,"clinic_label":null,"start_at":"2026-01-27T10:00:00+01:00","end_at":null,"timezone":"Europe/Amsterdam","is_remote":false,"remote_url":null,"created_at":"2026-01-20T09:00:00+01:00","updated_at":"2026-01-20T09:00:00+01:00"}]}
     * @response 200 scenario="Success (empty)" {"data":[]}
     * @response 404 scenario="Patient not found" {"message":"Not Found"}
     */
    public function index(Request $request, string $keycloakUserId): JsonResponse
    {
        [$person, $user] = $this->keycloakService->resolvePersonOrUser($keycloakUserId);

        if (is_null($person)) {
            if (! is_null($user)) {
                // No appointments for users without person association.
                return response()->json(['data' => []]);
            }

            abort(404);
        }

        $filter = strtolower((string) $request->query('filter', ''));
        $now = now();

        $orders = Order::query()
            ->whereIn('status', [OrderStatus::PLANNED->value, OrderStatus::APPROVED->value])
            ->whereNotNull('first_examination_at')
            ->whereHas('salesLead.persons', function ($q) use ($person) {
                $q->whereKey($person->id);
            })
            ->when($filter === 'future', fn ($q) => $q->where('first_examination_at', '>=', $now))
            ->when($filter === 'past', fn ($q) => $q->where('first_examination_at', '<', $now))
            ->orderBy('first_examination_at', 'asc')
            ->get();

        $timezone = config('app.timezone') ?: 'Europe/Amsterdam';

        $appointments = $orders->map(function (Order $order) use ($person, $timezone) {
            $firstAppointmentInClinic = $this->orderCheckService->retrieveClinicFromOrder($order, $person);
            $clinic = $firstAppointmentInClinic !== null
                ? Clinic::find($firstAppointmentInClinic)
                : null;

            return [
                'id'              => 'order-'.$order->id,
                'patient_id'      => (string) $person->id,
                'practitioner_id' => null,
                'clinic_id'       => $firstAppointmentInClinic,
                'clinic_label'    => $clinic?->label() ?? null,
                'start_at'        => $order->first_examination_at->toIso8601String(),
                'end_at'          => null,
                'timezone'        => $timezone,
                'is_remote'       => false,
                'remote_url'      => null,
                'created_at'      => $order->created_at->toIso8601String(),
                'updated_at'      => $order->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => AppointmentResource::collection($appointments),
        ]);
    }
}
