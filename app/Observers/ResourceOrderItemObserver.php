<?php

namespace App\Observers;

use App\Enums\OrderItemStatus;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\ResourceOrderItem;
use App\Services\Afb\AfbDispatchService;
use App\Services\OrderItemPurchasePriceService;
use App\Services\OrderStatusService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Repositories\ActivityRepository;

class ResourceOrderItemObserver
{
    public function __construct(
        private readonly OrderStatusService $orderStatusService,
        private readonly AfbDispatchService $afbDispatchService,
        private readonly ActivityRepository $activityRepository,
        private readonly OrderItemPurchasePriceService $orderItemPurchasePriceService,
    ) {}

    /**
     * Handle the ResourceOrderItem "created" event.
     */
    public function created(ResourceOrderItem $resourceOrderItem): void
    {
        $this->updateOrderItemStatus($resourceOrderItem);
        $this->capturePartnerProductPrice($resourceOrderItem);

        $this->logActivity($resourceOrderItem, 'created');
    }

    /**
     * Handle the ResourceOrderItem "updated" event.
     */
    public function updated(ResourceOrderItem $resourceOrderItem): void
    {
        if ($resourceOrderItem->wasChanged(['from', 'to'])) {
            $this->logActivity($resourceOrderItem, 'updated');
        }
    }

    /**
     * Handle the ResourceOrderItem "deleted" event.
     */
    public function deleted(ResourceOrderItem $resourceOrderItem): void
    {
        $this->updateOrderItemStatus($resourceOrderItem);

        $orderItem = $resourceOrderItem->orderItem ?? OrderItem::find($resourceOrderItem->orderitem_id);
        if ($orderItem) {
            $this->orderItemPurchasePriceService->clearPartnerProductLink($orderItem);
        }

        $this->logActivity($resourceOrderItem, 'deleted');
    }

    private function logActivity(ResourceOrderItem $resourceOrderItem, string $event): void
    {
        $orderItem = $resourceOrderItem->orderItem ?? OrderItem::find($resourceOrderItem->orderitem_id);

        if (! $orderItem?->order_id) {
            return;
        }

        $itemLabel = $orderItem->name ?: $orderItem->product?->name ?: "#{$orderItem->id}";
        $itemRef = "{$itemLabel} (order item #{$orderItem->id})";

        $fmt = fn ($dt) => $dt ? Carbon::parse($dt)->format('d-m-Y H:i') : null;

        $newFrom = $fmt($resourceOrderItem->from);
        $newTo = $fmt($resourceOrderItem->to);
        $oldFrom = $event === 'updated' ? $fmt($resourceOrderItem->getOriginal('from')) : null;
        $oldTo = $event === 'updated' ? $fmt($resourceOrderItem->getOriginal('to')) : null;

        $newLabel = implode(' – ', array_filter([$newFrom, $newTo])) ?: '-';
        $oldLabel = implode(' – ', array_filter([$oldFrom, $oldTo])) ?: '-';

        $title = match ($event) {
            'created' => "Ingepland: {$itemRef}",
            'deleted' => "Geplanning verwijderd: {$itemRef}",
            'updated' => "Ingepland gewijzigd: {$itemRef}",
        };

        $this->activityRepository->createSystem([
            'title'      => $title,
            'additional' => [
                'attribute' => $itemRef,
                'new'       => ['value' => $newFrom, 'label' => $newLabel],
                'old'       => ['value' => $oldFrom, 'label' => $oldLabel],
            ],
            'user_id'  => auth()->id() ?? 1,
            'order_id' => $orderItem->order_id,
        ]);
    }

    private function capturePartnerProductPrice(ResourceOrderItem $resourceOrderItem): void
    {
        $orderItem = $resourceOrderItem->orderItem;

        if (! $orderItem || ! $orderItem->product_id) {
            Log::error('Could not update purchase price, no related product');

            return;
        }

        $clinicId = $resourceOrderItem->resource()->with('clinicDepartment')->first()
            ?->clinicDepartment
            ?->clinic_id;

        if (! $clinicId) {
            return;
        }

        $partnerProduct = PartnerProduct::forClinicAndProduct($clinicId, $orderItem->product_id)
            ->with('purchasePrice')
            ->first();

        if (! $partnerProduct) {
            return;
        }

        $this->orderItemPurchasePriceService->linkPartnerProduct($orderItem, $partnerProduct);
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
