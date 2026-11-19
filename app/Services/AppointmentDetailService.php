<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductType;
use App\Models\ResourceOrderItem;
use App\Models\SalesLead;
use App\Support\AppointmentReference;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Webkul\Contact\Models\Person;

/**
 * Builds the afspraak-detail payload shared by the patient and clinic portal
 * (tickets/portaal/02-appointment-detail.md). Never exposes prices or other internal fields.
 */
class AppointmentDetailService
{
    public function __construct(private readonly OrderCheckService $orderCheckService) {}

    /**
     * Resolves an appointment id (booking id or legacy `order-{id}`) to its order and booking,
     * aborting with 404 when unknown or outside the caller's scope.
     *
     * @param  Closure(Builder): mixed  $orderScope  restricts the orders the caller may see
     * @param  Person|null  $patient  when given, only that person's own order items/bookings are visible
     * @return array{0: Order, 1: ResourceOrderItem|null}
     */
    public function resolve(string $appointmentId, Closure $orderScope, ?Person $patient = null): array
    {
        ['bookingId' => $bookingId, 'orderId' => $orderId] = AppointmentReference::decode($appointmentId);

        if ($bookingId !== null) {
            $booking = ResourceOrderItem::query()
                ->with(OrderCheckService::BOOKING_RELATIONS)
                ->whereHas('orderItem', function ($q) use ($orderScope, $patient) {
                    $q->whereHas('order', $orderScope)
                        ->when($patient, fn ($q) => $q->where('person_id', $patient->id));
                })
                ->find($bookingId);

            abort_if($booking === null, 404);

            return [$booking->orderItem->order, $booking];
        }

        abort_if($orderId === null, 404);

        $order = Order::query()->where($orderScope)->find($orderId);

        abort_if($order === null, 404);

        return [$order, $this->orderCheckService->retrieveFirstBookingForOrder($order, $patient)];
    }

    /**
     * @return array{
     *     id: string,
     *     type: ProductType|null,
     *     start_at: Carbon|null,
     *     end_at: Carbon|null,
     *     sale: SalesLead|null,
     *     clinic: Clinic|null,
     *     registration_note: string|null,
     *     items: Collection<int, OrderItem>,
     * }
     */
    public function build(Order $order, ?ResourceOrderItem $booking, ?Person $patient = null): array
    {
        $order->loadMissing([
            'salesLead',
            'orderItems'                => fn ($q) => $q->notLost(),
            'orderItems.resourceOrderItems',
            'orderItems.product.productType',
            'orderItems.person',
        ]);

        // An appointment belongs to one person: the patient themself, or the booking's owner for the clinic.
        $personId = $patient?->id ?? $booking?->orderItem?->person_id;
        $items = $this->itemsForBooking($order, $booking, $personId);
        $department = $booking?->resource?->clinicDepartment;

        return [
            'id'                => AppointmentReference::encode($order, $booking),
            'type'              => $items->first()?->resolvedProductType(),
            'start_at'          => $booking?->from,
            'end_at'            => $booking?->to,
            'sale'              => $order->salesLead,
            'clinic'            => $booking?->resource?->clinic,
            'registration_note' => $department?->order_confirmation_note,
            'items'             => $items,
        ];
    }

    /**
     * Order items booked into the same slot as $booking (same `from`): a visit can bundle
     * several products at once. Only items of $personId (when known). Falls back to every
     * (person-scoped) active item when there is no booking yet, or none shares its slot.
     *
     * @return Collection<int, OrderItem>
     */
    private function itemsForBooking(Order $order, ?ResourceOrderItem $booking, ?int $personId): Collection
    {
        $activeItems = $personId === null
            ? $order->orderItems
            : $order->orderItems->where('person_id', $personId);

        if ($booking === null) {
            return $activeItems->values();
        }

        $slotItems = $activeItems->filter(
            fn (OrderItem $item) => $item->resourceOrderItems->contains(
                fn (ResourceOrderItem $roi) => $roi->from?->equalTo($booking->from)
            )
        );

        return $slotItems->isNotEmpty() ? $slotItems->values() : $activeItems->values();
    }
}
