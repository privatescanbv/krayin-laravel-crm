<?php

namespace App\Observers;

use App\Enums\OrderItemStatus;
use App\Models\OrderItem;
use App\Services\OrderCheckService;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Contact\Models\Person;
use Webkul\Product\Models\Product;

class OrderItemObserver
{
    public function __construct(
        protected OrderCheckService $orderCheckService,
        private readonly ActivityRepository $activityRepository,
    ) {}

    /**
     * Handle the OrderItem "created" event.
     */
    public function created(OrderItem $orderItem): void
    {
        $this->updateOrderItemStatus($orderItem);
        $this->updatePartnerProductChecks($orderItem);
        $orderItem->order?->recalculateTotalPrice();
    }

    /**
     * Handle the OrderItem "updated" event.
     */
    public function updated(OrderItem $orderItem): void
    {
        // If status changed to LOST, free up planning slots
        if ($orderItem->wasChanged('status') && $orderItem->status === OrderItemStatus::LOST) {
            DB::table('resource_orderitem')
                ->where('orderitem_id', $orderItem->id)
                ->delete();
        }

        // Check if product_id changed, then recalculate status
        if ($orderItem->wasChanged('product_id')) {
            $this->updateOrderItemStatus($orderItem);
            $this->updatePartnerProductChecks($orderItem);
        }

        if ($orderItem->wasChanged('status') || $orderItem->wasChanged('total_price')) {
            $orderItem->order?->recalculateTotalPrice();
        }

        // After updating an order item, check if we need to update the parent order status
        $this->updateParentOrderStatus($orderItem);

        $this->logFieldChanges($orderItem);
    }

    /**
     * Handle the OrderItem "deleted" event.
     */
    public function deleted(OrderItem $orderItem): void
    {
        $orderItem->order?->recalculateTotalPrice();
        // After deleting an order item, check if we need to update the parent order status
        $this->updateParentOrderStatus($orderItem);
        $this->updatePartnerProductChecks($orderItem);
    }

    private function logFieldChanges(OrderItem $orderItem): void
    {
        if (! $orderItem->order_id) {
            return;
        }

        $fields = [
            'name'        => 'Naam',
            'description' => 'Omschrijving',
            'product_id'  => 'Product',
            'quantity'    => 'Aantal',
            'total_price' => 'Prijs',
            'status'      => 'Status',
            'person_id'   => 'Patiënt',
        ];

        $itemLabel = $orderItem->name ?: $orderItem->product?->name ?: "#{$orderItem->id}";
        $itemRef = "{$itemLabel} (order item #{$orderItem->id})";

        foreach ($fields as $field => $label) {
            if (! $orderItem->wasChanged($field)) {
                continue;
            }

            $oldRaw = $orderItem->getOriginal($field);
            $newRaw = $orderItem->getAttribute($field);

            [$oldLabel, $newLabel] = match ($field) {
                'product_id' => [
                    Product::find($oldRaw)?->name,
                    Product::find($newRaw)?->name,
                ],
                'person_id' => [
                    Person::find($oldRaw)?->name,
                    Person::find($newRaw)?->name,
                ],
                'status' => [
                    $oldRaw instanceof OrderItemStatus ? $oldRaw->label() : ($oldRaw !== null ? (OrderItemStatus::tryFrom((string) $oldRaw)?->label() ?? (string) $oldRaw) : null),
                    $newRaw instanceof OrderItemStatus ? $newRaw->label() : ($newRaw !== null ? (OrderItemStatus::tryFrom((string) $newRaw)?->label() ?? (string) $newRaw) : null),
                ],
                'total_price' => [
                    $oldRaw !== null ? '€ '.number_format((float) $oldRaw, 2, ',', '.') : null,
                    $newRaw !== null ? '€ '.number_format((float) $newRaw, 2, ',', '.') : null,
                ],
                default => [(string) ($oldRaw ?? ''), (string) ($newRaw ?? '')],
            };

            if (empty($oldLabel) && empty($newLabel)) {
                continue;
            }

            $this->activityRepository->createSystem([
                'title'      => "{$label} gewijzigd: {$itemRef}",
                'additional' => [
                    'attribute' => $label,
                    'new'       => ['value' => $newRaw, 'label' => $newLabel ?: '-'],
                    'old'       => ['value' => $oldRaw, 'label' => $oldLabel ?: '-'],
                ],
                'user_id'  => auth()->id() ?? 1,
                'order_id' => $orderItem->order_id,
            ]);
        }
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
