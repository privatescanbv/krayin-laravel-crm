<?php

namespace App\Observers;

use App\Enums\OrderItemStatus;
use App\Enums\PurchasePriceType;
use App\Models\PartnerProduct;
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
        $this->capturePartnerProductPrice($resourceOrderItem);

        $order = $resourceOrderItem->orderItem?->order;
    }

    /**
     * Handle the ResourceOrderItem "deleted" event.
     */
    public function deleted(ResourceOrderItem $resourceOrderItem): void
    {
        $this->updateOrderItemStatus($resourceOrderItem);
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
        $partnerPrice = PartnerProduct::forClinicAndProduct($clinicId, $orderItem->product_id)
            ->with('purchasePrice')
            ->first()
            ?->purchasePrice;

        if (! $partnerPrice) {
            return;
        }

        $orderItem->purchasePrice()->updateOrCreate([], [
            'type'                      => PurchasePriceType::MAIN,
            'purchase_price_misc'       => $partnerPrice->purchase_price_misc,
            'purchase_price_doctor'     => $partnerPrice->purchase_price_doctor,
            'purchase_price_cardiology' => $partnerPrice->purchase_price_cardiology,
            'purchase_price_clinic'     => $partnerPrice->purchase_price_clinic,
            'purchase_price_radiology'  => $partnerPrice->purchase_price_radiology,
            'purchase_price'            => $partnerPrice->purchase_price,
        ]);
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
