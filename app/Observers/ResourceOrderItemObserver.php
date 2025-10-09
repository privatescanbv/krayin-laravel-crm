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
        $this->updateOrderRegelStatus($resourceOrderItem);
    }

    /**
     * Handle the ResourceOrderItem "deleted" event.
     */
    public function deleted(ResourceOrderItem $resourceOrderItem): void
    {
        $this->updateOrderRegelStatus($resourceOrderItem);
    }

    /**
     * Update the OrderRegel status when a ResourceOrderItem is created or deleted.
     */
    private function updateOrderRegelStatus(ResourceOrderItem $resourceOrderItem): void
    {
        if (! $resourceOrderItem->orderitem_id) {
            return;
        }

        // Check if there are still ResourceOrderItems for this OrderRegel
        $hasResourceOrderItem = DB::table('resource_orderitem')
            ->where('orderitem_id', $resourceOrderItem->orderitem_id)
            ->exists();

        $newStatus = $hasResourceOrderItem
            ? OrderItemStatus::INGEPLAND
            : $this->calculateStatusWithoutPlanning($resourceOrderItem->orderitem_id);

        // Update the OrderRegel status
        DB::table('order_regels')
            ->where('id', $resourceOrderItem->orderitem_id)
            ->update(['status' => $newStatus->value]);

        // Trigger parent order status update
        $orderRegel = DB::table('order_regels')
            ->where('id', $resourceOrderItem->orderitem_id)
            ->first();

        if ($orderRegel && $orderRegel->order_id) {
            // Touch the order to trigger status update
            DB::table('orders')
                ->where('id', $orderRegel->order_id)
                ->update(['updated_at' => now()]);
        }
    }

    /**
     * Calculate status for an OrderRegel without planning.
     */
    private function calculateStatusWithoutPlanning(int $orderRegelId): OrderItemStatus
    {
        $orderRegel = DB::table('order_regels')
            ->where('id', $orderRegelId)
            ->first();

        if (! $orderRegel || ! $orderRegel->product_id) {
            return OrderItemStatus::NIEUW;
        }

        // Check if product has partner products
        $hasPartnerProducts = DB::table('partner_products')
            ->where('product_id', $orderRegel->product_id)
            ->exists();

        return $hasPartnerProducts
            ? OrderItemStatus::MOET_WORDEN_INGEPLAND
            : OrderItemStatus::NIEUW;
    }
}
