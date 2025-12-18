<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class OrderStatusService
{
    /**
     * Calculate the correct status for an Order.
     *
     * Rules:
     * - Only order items that can be planned (have partner products) are considered
     * - If all planable order items are PLANNED → Order status is PLANNED
     * - If any planable order item is not PLANNED → Order status is NEW
     * - If no planable order items exist → Order status is NEW
     */
    public function calculate(Order $order): OrderStatus
    {
        // Get order items with their product information
        //        $orderItems = DB::table('order_items')
        //            ->where('order_id', $order->id)
        //            ->join('products', 'order_items.product_id', '=', 'products.id')
        //            ->leftJoin('partner_products', 'products.id', '=', 'partner_products.product_id')
        //            ->select(
        //                'order_items.id',
        //                'order_items.status',
        //                'order_items.product_id',
        //                DB::raw('COUNT(partner_products.id) as partner_product_count')
        //            )
        //            ->groupBy('order_items.id', 'order_items.status', 'order_items.product_id')
        //            ->get();

        $orderItems = OrderItem::query()
            ->where('order_id', $order->id)
            ->select('order_items.id', 'order_items.status', 'order_items.product_id')
            ->selectRaw('COUNT(partner_products.id) AS partner_product_count')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->leftJoin('partner_products', 'products.id', '=', 'partner_products.product_id')
            ->groupBy('order_items.id', 'order_items.status', 'order_items.product_id')
            ->get();

        if ($orderItems->isEmpty()) {
            return OrderStatus::NEW;
        }

        // Filter to only planable items (items with partner products)
        $planableItems = $orderItems->filter(function (OrderItem $item) {
            return $item->isPlannable();
        });

        // If there are no planable items, order status is NEW
        if ($planableItems->isEmpty()) {
            return OrderStatus::NEW;
        }

        // Check if all planable items are planned
        foreach ($planableItems as $orderItem) {
            if ($orderItem->status !== OrderItemStatus::PLANNED) {
                // At least one planable item is not planned, so order should be NEW
                return OrderStatus::NEW;
            }
        }

        // All planable items are planned
        return OrderStatus::PLANNED;
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
            $order->status = OrderStatus::tryFrom($row->status);
        }

        $this->recalculateAndPersist($order);
    }
}
