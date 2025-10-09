<?php

namespace App\Observers;

use App\Enums\OrderItemStatus;
use App\Models\ResourceOrderItem;
use Illuminate\Support\Facades\DB;

class ResourceOrderItemObserver
{
    /**
     * Handle the ResourceOrderItem "created" event.
     */
    public function created(ResourceOrderItem $resourceOrderItem): void
    {
        $this->updateOrderItemStatus($resourceOrderItem);
    }

    /**
     * Handle the ResourceOrderItem "deleted" event.
     */
    public function deleted(ResourceOrderItem $resourceOrderItem): void
    {
        $this->updateOrderItemStatus($resourceOrderItem);
    }

    /**
     * Update the OrderItem status when a ResourceOrderItem is created or deleted.
     */
    private function updateOrderItemStatus(ResourceOrderItem $resourceOrderItem): void
    {
        if (! $resourceOrderItem->order_item_id) {
            return;
        }

        // Check if there are still ResourceOrderItems for this OrderItem
        $hasResourceOrderItem = DB::table('resource_order_items')
            ->where('order_item_id', $resourceOrderItem->order_item_id)
            ->exists();

        $newStatus = $hasResourceOrderItem
            ? OrderItemStatus::INGEPLAND
            : $this->calculateStatusWithoutPlanning($resourceOrderItem->order_item_id);

        // Update the OrderItem status
        DB::table('order_items')
            ->where('id', $resourceOrderItem->order_item_id)
            ->update(['status' => $newStatus->value]);

        // Trigger parent order status update
        $orderItem = DB::table('order_items')
            ->where('id', $resourceOrderItem->order_item_id)
            ->first();

        if ($orderItem && $orderItem->order_id) {
            // Touch the order to trigger status update
            DB::table('orders')
                ->where('id', $orderItem->order_id)
                ->update(['updated_at' => now()]);
        }
    }

    /**
     * Calculate status for an OrderItem without planning.
     */
    private function calculateStatusWithoutPlanning(int $orderItemId): OrderItemStatus
    {
        $orderItem = DB::table('order_items')
            ->where('id', $orderItemId)
            ->first();

        if (! $orderItem || ! $orderItem->product_id) {
            return OrderItemStatus::NIEUW;
        }

        // Check if product has partner products
        $hasPartnerProducts = DB::table('partner_products')
            ->where('product_id', $orderItem->product_id)
            ->exists();

        return $hasPartnerProducts
            ? OrderItemStatus::MOET_WORDEN_INGEPLAND
            : OrderItemStatus::NIEUW;
    }
}
