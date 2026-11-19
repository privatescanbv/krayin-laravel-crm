<?php

namespace App\Http\Resources;

use App\Enums\Departments;
use App\Models\Clinic;
use App\Models\ClinicDepartment;
use App\Models\Order;
use App\Models\ResourceOrderItem;
use App\Support\AppointmentReference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Webkul\Contact\Models\Person;

/**
 * Resource for patient portal appointment list items, derived from Orders.
 *
 * Expected resource payload shape:
 *   [
 *     'order'      => Order,
 *     'booking'    => ResourceOrderItem|null, // the order's primary appointment slot
 *     'clinic'     => Clinic|null,             // already eager-loaded via the booking; never queried here
 *     'department' => ClinicDepartment|null,
 *     'person'     => Person,
 *   ]
 */
class PatientPortalAppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this['order'];
        /** @var Person $person */
        $person = $this['person'];
        /** @var ResourceOrderItem|null $booking */
        $booking = $this['booking'] ?? null;
        /** @var Clinic|null $clinic */
        $clinic = $this['clinic'] ?? null;
        /** @var ClinicDepartment|null $department */
        $department = $this['department'] ?? null;

        return [
            'id'                => AppointmentReference::encode($order, $booking),
            'patient_id'        => (string) $person->id,
            'clinic_id'         => $clinic ? (string) $clinic->id : null,
            'clinic_ref'        => $clinic ? ClinicResource::make($clinic) : null,
            'start_at'          => $order->firstExaminationCarbon()?->toIso8601String(),
            'end_at'            => $booking?->to?->toIso8601String(),
            'timezone'          => config('app.timezone'),
            'created_at'        => $order->created_at->toIso8601String(),
            'updated_at'        => $order->updated_at?->toIso8601String(),
            'department'        => $order->isHerniapoli() ? Departments::HERNIA->value : Departments::PRIVATESCAN->value,
            'registration_note' => $department?->order_confirmation_note,
        ];
    }
}
