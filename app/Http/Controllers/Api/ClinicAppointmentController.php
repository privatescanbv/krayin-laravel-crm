<?php

namespace App\Http\Controllers\Api;

use App\Actions\Clinic\SendClinicForgotPasswordMailAction;
use App\Enums\RoleCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentDetailResource;
use App\Models\Order;
use App\Models\ResourceOrderItem;
use App\Services\AppointmentDetailService;
use App\Services\OrderCheckService;
use App\Support\AppointmentReference;
use Webkul\User\Models\User;

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
    public function __construct(
        private readonly OrderCheckService $orderCheckService,
        private readonly AppointmentDetailService $appointmentDetailService,
    ) {}

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

        ['bookingId' => $bookingId, 'orderId' => $orderId] = AppointmentReference::decode($appointmentId);

        if ($bookingId !== null) {
            $booking = ResourceOrderItem::query()
                ->with(['resource.clinicDepartment', 'resource.clinic.visitAddress', 'orderItem.order'])
                ->whereHas('orderItem.order.salesLead', fn ($q) => $q->forPortalDoctor($doctor))
                ->find($bookingId);

            abort_if($booking === null, 404);

            $order = $booking->orderItem->order;
        } elseif ($orderId !== null) {
            $order = Order::query()
                ->whereHas('salesLead', fn ($q) => $q->forPortalDoctor($doctor))
                ->find($orderId);

            abort_if($order === null, 404);

            $booking = $this->orderCheckService->retrieveFirstBookingForOrder($order, null);
        } else {
            abort(404);
        }

        return new AppointmentDetailResource($this->appointmentDetailService->build($order, $booking));
    }

    private function doctor(string $keycloakUserId): User
    {
        $user = SendClinicForgotPasswordMailAction::clinicUsers()
            ->where('keycloak_user_id', $keycloakUserId)
            ->with('role')
            ->firstOrFail();

        abort_unless($user->role?->code === RoleCode::ARTS, 403);

        return $user;
    }
}
