<?php

namespace App\Observers;

use App\Enums\OrderItemStatus;
use App\Models\OrderRegel;
use Illuminate\Support\Facades\DB;

class OrderRegelObserver
{
    /**
     * Handle the OrderRegel "created" event.
     */
    public function created(OrderRegel $orderRegel): void
    {
        $this->updateOrderRegelStatus($orderRegel);
    }

    /**
     * Handle the OrderRegel "updated" event.
     */
    public function updated(OrderRegel $orderRegel): void
    {
        // Check if product_id changed, then recalculate status
        if ($orderRegel->wasChanged('product_id')) {
            $this->updateOrderRegelStatus($orderRegel);
        }

        // After updating an order regel, check if we need to update the parent order status
        $this->updateParentOrderStatus($orderRegel);
    }

    /**
     * Update the status of an OrderRegel based on business rules.
     */
    private function updateOrderRegelStatus(OrderRegel $orderRegel): void
    {
        // Don't auto-update if status was manually set (is dirty)
        if ($orderRegel->isDirty('status') && $orderRegel->status !== null) {
            return;
        }

        $newStatus = $this->calculateStatus($orderRegel);

        if ($orderRegel->status !== $newStatus) {
            // Use direct DB update to avoid triggering observer again
            DB::table('order_regels')
                ->where('id', $orderRegel->id)
                ->update(['status' => $newStatus->value]);

            // Refresh the model to reflect the change
            $orderRegel->refresh();
        }
    }

    /**
     * Calculate the correct status for an OrderRegel.
     */
    private function calculateStatus(OrderRegel $orderRegel): OrderItemStatus
    {
        // Check if it has a ResourceOrderItem (is planned)
        $hasResourceOrderItem = DB::table('resource_orderitem')
            ->where('orderitem_id', $orderRegel->id)
            ->exists();

        if ($hasResourceOrderItem) {
            return OrderItemStatus::INGEPLAND;
        }

        // Check if product has partner products (needs planning)
        if ($orderRegel->product_id) {
            $hasPartnerProducts = DB::table('partner_products')
                ->where('product_id', $orderRegel->product_id)
                ->exists();

            if ($hasPartnerProducts) {
                return OrderItemStatus::MOET_WORDEN_INGEPLAND;
            }
        }

        return OrderItemStatus::NIEUW;
    }

    /**
     * Update the parent Order status after OrderRegel change.
     */
    private function updateParentOrderStatus(OrderRegel $orderRegel): void
    {
        if ($orderRegel->order) {
            $order = $orderRegel->order;
            // Trigger the OrderObserver by touching the order
            $order->touch();
        }
    }
}
