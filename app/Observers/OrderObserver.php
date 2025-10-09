<?php

namespace App\Observers;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderObserver
{
    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // Don't auto-update if status was manually changed
        if ($order->wasChanged('status')) {
            return;
        }

        $this->updateOrderStatus($order);
    }

    /**
     * Update the Order status based on OrderRegel statuses.
     */
    private function updateOrderStatus(Order $order): void
    {
        $newStatus = $this->calculateOrderStatus($order);

        if ($order->status !== $newStatus) {
            // Use direct DB update to avoid triggering observer again
            DB::table('orders')
                ->where('id', $order->id)
                ->update(['status' => $newStatus->value]);
        }
    }

    /**
     * Calculate the correct status for an Order.
     * Order should be INGEPLAND when all items are ready (INGEPLAND or don't need planning).
     */
    private function calculateOrderStatus(Order $order): OrderStatus
    {
        // Get all order items
        $orderRegels = DB::table('order_regels')
            ->where('order_id', $order->id)
            ->get();

        if ($orderRegels->isEmpty()) {
            return OrderStatus::NIEUW;
        }

        // Check if all items are ready (INGEPLAND or NIEUW)
        $allReady = true;
        foreach ($orderRegels as $orderRegel) {
            $status = $orderRegel->status;

            // If any item needs to be planned (MOET_WORDEN_INGEPLAND), order is not ready
            if ($status === OrderItemStatus::MOET_WORDEN_INGEPLAND->value) {
                $allReady = false;
                break;
            }
        }

        // If all items are ready, set order to INGEPLAND
        if ($allReady) {
            return OrderStatus::INGEPLAND;
        }

        return $order->status ?? OrderStatus::NIEUW;
    }
}
