<?php

namespace App\Observers;

use App\Enums\OrderItemStatus;
use App\Models\ResourceOrderItem;
use App\Services\Afb\AfbDispatchService;
use App\Services\OrderStatusService;
use Illuminate\Support\Facades\DB;

class ResourceOrderItemObserver
{
    public function __construct(
        private readonly OrderStatusService $orderStatusService,
        private readonly AfbDispatchService $afbDispatchService
    ) {}

    /**
     * Handle the ResourceOrderItem "created" event.
     */
    public function created(ResourceOrderItem $resourceOrderItem): void
    {
        $this->updateOrderItemStatus($resourceOrderItem);

        $order = $resourceOrderItem->orderItem?->order;
        if ($order) {
            $this->afbDispatchService->queueLateBookingForOrder($order);
        }
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
        if (! $resourceOrderItem->orderitem_id) {
            return;
        }

        // Check if there are still ResourceOrderItems for this OrderItem
        $hasResourceOrderItem = DB::table('resource_orderitem')
            ->where('orderitem_id', $resourceOrderItem->orderitem_id)
            ->exists();

        $newStatus = $hasResourceOrderItem
            ? OrderItemStatus::PLANNED
            : OrderItemStatus::NEW;

        // Update the OrderItem status
        DB::table('order_items')
            ->where('id', $resourceOrderItem->orderitem_id)
            ->update(['status' => $newStatus->value]);

        // Trigger parent order status update
        $orderItem = DB::table('order_items')
            ->where('id', $resourceOrderItem->orderitem_id)
            ->first();

        if ($orderItem && $orderItem->order_id) {
            $this->orderStatusService->recalculateAndPersistById((int) $orderItem->order_id);
        }
    }
}
