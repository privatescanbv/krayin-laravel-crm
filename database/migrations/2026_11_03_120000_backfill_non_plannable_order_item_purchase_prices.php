<?php

use App\Models\OrderItem;
use App\Services\OrderItemPurchasePriceService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $service = app(OrderItemPurchasePriceService::class);

        OrderItem::query()
            ->whereNotNull('product_id')
            ->whereDoesntHave('purchasePrice', fn ($query) => $query->where('purchase_price', '>', 0))
            ->with(['product.resourceType', 'product.partnerProducts.resourceType', 'resourceType', 'purchasePrice', 'partnerProduct.purchasePrice'])
            ->lazyById()
            ->each(function (OrderItem $orderItem) use ($service) {
                if ($orderItem->isPlannable() || $orderItem->hasEnteredPurchasePrice()) {
                    return;
                }

                if ($orderItem->partner_product_id === null) {
                    $service->relinkPartnerProduct($orderItem);

                    return;
                }

                // Existing link: use it as is. A soft-deleted partner product is not resolvable here.
                if ($orderItem->partnerProduct !== null) {
                    $service->linkPartnerProduct($orderItem, $orderItem->partnerProduct);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
