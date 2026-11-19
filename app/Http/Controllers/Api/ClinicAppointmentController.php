<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesClinicDoctor;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentDetailResource;
use App\Services\AppointmentDetailService;

/**
 * Afspraak-detail in the clinic portal (tickets/portaal/02-appointment-detail.md), Herniapoli
 * doctors only. Scoped the same way as {@see ClinicSalesLeadController}: via
 * `SalesLead::forPortalDoctor()`. The ticket's proposed `portal/appointments/{id}`
 * (`SalesLead::scopeVisibleInPortalFor`, auth for every clinic role) needs the not-yet-built
 * `portal/*` auth foundation (G-1a); this ships on the already-shipped `clinic/{keycloakUserId}`
 * doctor auth instead, using the same shared Resource/service so moving it later is a route change.
 */
class ClinicAppointmentController extends Controller
{
    use ResolvesClinicDoctor;

    public function __construct(private readonly AppointmentDetailService $appointmentDetailService) {}

    /**
     * @group Clinic appointments
     *
     * @urlParam keycloakUserId string required The Keycloak user ID of the doctor.
     * @urlParam appointmentId string required The appointment id, as returned by the patient appointments list.
     *
     * @response 403 scenario="User is not a doctor"
     * @response 404 scenario="Unknown user, or appointment not visible for this doctor"
     */
    public function show(string $keycloakUserId, string $appointmentId): AppointmentDetailResource
    {
        $doctor = $this->doctor($keycloakUserId);

        [$order, $booking] = $this->appointmentDetailService->resolve(
            $appointmentId,
            fn ($q) => $q->whereHas('salesLead', fn ($q) => $q->forPortalDoctor($doctor)),
        );

        return new AppointmentDetailResource($this->appointmentDetailService->build($order, $booking));
    }
}
