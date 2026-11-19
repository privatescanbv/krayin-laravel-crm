<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductType;
use App\Models\ResourceOrderItem;
use App\Models\SalesLead;
use App\Support\AppointmentReference;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the afspraak-detail payload shared by the patient and clinic portal
 * (tickets/portaal/02-appointment-detail.md). Never exposes prices or other internal fields.
 */
class AppointmentDetailService
{
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
    public function build(Order $order, ?ResourceOrderItem $booking): array
    {
        $order->loadMissing([
            'salesLead',
            'orderItems'                => fn ($q) => $q->notLost(),
            'orderItems.resourceOrderItems',
            'orderItems.product.productType',
            'orderItems.person',
        ]);

        $items = $this->itemsForBooking($order, $booking);
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
     * several products at once. Falls back to every active item when there is no booking yet,
     * or none shares its slot.
     *
     * @return Collection<int, OrderItem>
     */
    private function itemsForBooking(Order $order, ?ResourceOrderItem $booking): Collection
    {
        $activeItems = $order->orderItems;

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
