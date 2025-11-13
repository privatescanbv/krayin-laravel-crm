<?php

namespace App\Observers;

use App\Enums\OrderItemStatus;
use App\Models\OrderItem;
use App\Services\OrderCheckService;
use Illuminate\Support\Facades\DB;

class OrderItemObserver
{
    public function __construct(
        protected OrderCheckService $orderCheckService
    ) {}

    /**
     * Handle the OrderItem "created" event.
     */
    public function created(OrderItem $orderItem): void
    {
        $this->updateOrderItemStatus($orderItem);
        $this->updatePartnerProductChecks($orderItem);
    }

    /**
     * Handle the OrderItem "updated" event.
     */
    public function updated(OrderItem $orderItem): void
    {
        // Check if product_id changed, then recalculate status
        if ($orderItem->wasChanged('product_id')) {
            $this->updateOrderItemStatus($orderItem);
            $this->updatePartnerProductChecks($orderItem);
        }

        // After updating an order item, check if we need to update the parent order status
        $this->updateParentOrderStatus($orderItem);
    }

    /**
     * Handle the OrderItem "deleted" event.
     */
    public function deleted(OrderItem $orderItem): void
    {
        // After deleting an order item, check if we need to update the parent order status
        $this->updateParentOrderStatus($orderItem);
        $this->updatePartnerProductChecks($orderItem);
    }

    /**
     * Update the status of an OrderItem based on business rules.
     */
    private function updateOrderItemStatus(OrderItem $orderItem): void
    {
        // Don't auto-update if status was manually set (is dirty)
        if ($orderItem->isDirty('status') && $orderItem->status !== null) {
            return;
        }

        $newStatus = $this->calculateStatus($orderItem);

        if ($orderItem->status !== $newStatus) {
            // Use direct DB update to avoid triggering observer again
            DB::table('order_items')
                ->where('id', $orderItem->id)
                ->update(['status' => $newStatus->value]);

            // Refresh the model to reflect the change
            $orderItem->refresh();
        }
    }

    /**
     * Calculate the correct status for an OrderItem.
     */
    private function calculateStatus(OrderItem $orderItem): OrderItemStatus
    {
        // Check if it has a ResourceOrderItem (is planned)
        $hasResourceOrderItem = DB::table('resource_orderitem')
            ->where('orderitem_id', $orderItem->id)
            ->exists();

        if ($hasResourceOrderItem) {
            return OrderItemStatus::PLANNED;
        }

        // If not planned, status is NEW
        return OrderItemStatus::NEW;
    }

    /**
     * Update the parent Order status after OrderItem change.
     */
    private function updateParentOrderStatus(OrderItem $orderItem): void
    {
        if ($orderItem->order) {
            $order = $orderItem->order;
            // Trigger the OrderObserver by touching the order
            $order->touch();
        }
    }

    /**
     * Update partner product checks for the order
     */
    private function updatePartnerProductChecks(OrderItem $orderItem): void
    {
        if ($orderItem->order) {
            $this->orderCheckService->updatePartnerProductChecks($orderItem->order);
        }
    }
}
