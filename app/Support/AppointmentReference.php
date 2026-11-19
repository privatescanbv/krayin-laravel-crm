<?php

namespace App\Support;

use App\Models\Order;
use App\Models\ResourceOrderItem;

/**
 * Stable appointment id (tickets/portaal/02-appointment-detail.md, V5): a booking
 * (`ResourceOrderItem`) id. Orders without a booking yet (only a manual
 * `first_examination_at` override) fall back to the legacy `order-{id}` form, which
 * {@see decode()} keeps accepting so previously bookmarked links stay valid.
 */
final class AppointmentReference
{
    private function __construct() {}

    public static function encode(Order $order, ?ResourceOrderItem $booking): string
    {
        return $booking ? (string) $booking->id : 'order-'.$order->id;
    }

    /**
     * @return array{bookingId: int|null, orderId: int|null} exactly one key is non-null for a
     *                                                       recognized id, both null otherwise.
     */
    public static function decode(string $id): array
    {
        if (ctype_digit($id)) {
            return ['bookingId' => (int) $id, 'orderId' => null];
        }

        if (preg_match('/^order-(\d+)$/', $id, $matches) === 1) {
            return ['bookingId' => null, 'orderId' => (int) $matches[1]];
        }

        return ['bookingId' => null, 'orderId' => null];
    }
}
