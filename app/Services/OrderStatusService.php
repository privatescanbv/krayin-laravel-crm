<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderStatusService
{
    /**
     * Calculate the correct status for an Order.
     */
    public function calculate(Order $order): OrderStatus
    {
        $orderItems = DB::table('order_items')
            ->where('order_id', $order->id)
            ->get(['status']);

        if ($orderItems->isEmpty()) {
            return OrderStatus::NIEUW;
        }

        foreach ($orderItems as $orderItem) {
            if ((int) $orderItem->status === OrderItemStatus::MOET_WORDEN_INGEPLAND->value) {
                // Any item that still must be planned means the order is not fully ready
                return $order->status ?? OrderStatus::NIEUW;
            }
        }

        return OrderStatus::INGEPLAND;
    }

    /**
     * Recalculate and persist the status if different.
     */
    public function recalculateAndPersist(Order $order): void
    {
        $newStatus = $this->calculate($order);

        if ($order->status !== $newStatus) {
            DB::table('orders')
                ->where('id', $order->id)
                ->update(['status' => $newStatus->value, 'updated_at' => now()]);
        }
    }

    /**
     * Convenience by id.
     */
    public function recalculateAndPersistById(int $orderId): void
    {
        $order = new Order;
        $order->id = $orderId;
        // Fetch current status minimally
        $row = DB::table('orders')->where('id', $orderId)->first(['status']);
        if ($row) {
            $order->status = OrderStatus::tryFrom((int) $row->status);
        }

        $this->recalculateAndPersist($order);
    }
}
