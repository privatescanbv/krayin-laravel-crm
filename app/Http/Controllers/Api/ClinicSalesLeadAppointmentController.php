<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesClinicDoctor;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClinicSalesAppointmentResource;
use App\Models\SalesLead;
use App\Services\AppointmentDetailService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Afspraken van één patiënt op de sale-kaart (tickets/clinic-portaal/05-tab-termine.md).
 */
class ClinicSalesLeadAppointmentController extends Controller
{
    use ResolvesClinicDoctor;

    public function __construct(private readonly AppointmentDetailService $appointmentDetailService) {}

    /**
     * All active order items of one patient of the sale, over all orders of the sale, one line per booking.
     * `status`: `scheduled` (booking with a time; `id` works with `GET clinic/{keycloakUserId}/appointments/{appointmentId}`),
     * `unscheduled` (needs planning, no booking yet; `expected_date` is the order's first-examination date when known) or
     * `not_applicable` (no appointment needed). Upcoming first, then the past, then unscheduled, then not applicable.
     * Lost/incorrect items and items without a person are never shown. No prices or internal fields.
     *
     * @group Clinic sales
     *
     * @urlParam keycloakUserId string required The Keycloak user ID of the doctor.
     * @urlParam saleId integer required The sale id (must be assigned to this doctor and visible to the role).
     * @urlParam personId integer required The id of a person of the sale (`persons[].id` of the sale).
     *
     * @response 200 scenario="Success" {"data":[{"id":"881","order_item_id":55,"order_id":12,"label":"MRI lumbale wervelkolom","type":{"id":4,"label":"MRI"},"status":"scheduled","start_at":"2026-12-01T09:30:00+01:00","end_at":"2026-12-01T10:15:00+01:00","expected_date":null,"clinic":{"id":2,"label":"Kliniek Voorbeeld","address":"Straat 1\n1234 AB Plaats"}},{"id":null,"order_item_id":56,"order_id":12,"label":"Consult","type":null,"status":"unscheduled","start_at":null,"end_at":null,"expected_date":"2026-12-20","clinic":null}],"meta":{"counts":{"scheduled":1,"unscheduled":1,"not_applicable":0}}}
     * @response 403 scenario="User is not a doctor, or Bearer token belongs to another user"
     * @response 404 scenario="Unknown user, sale not assigned to / not visible for this doctor, or person not on this sale"
     */
    public function index(string $keycloakUserId, int $saleId, int $personId): AnonymousResourceCollection
    {
        $sale = SalesLead::query()->forPortalDoctor($this->doctor($keycloakUserId))->findOrFail($saleId);
        $person = $sale->persons()->findOrFail($personId);
        $lines = $this->appointmentDetailService->forPerson($sale, $person);

        return ClinicSalesAppointmentResource::collection($lines)->additional(['meta' => ['counts' => [
            'scheduled'      => $lines->where('status', AppointmentDetailService::SCHEDULED)->count(),
            'unscheduled'    => $lines->where('status', AppointmentDetailService::UNSCHEDULED)->count(),
            'not_applicable' => $lines->where('status', AppointmentDetailService::NOT_APPLICABLE)->count(),
        ]]]);
    }
}
