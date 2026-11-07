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
            ->whereNull('partner_product_id')
            ->with(['product.resourceType', 'product.partnerProducts.resourceType', 'resourceType', 'purchasePrice'])
            ->each(function (OrderItem $orderItem) use ($service) {
                if ($orderItem->isPlannable()) {
                    return;
                }

                $service->relinkPartnerProduct($orderItem);
            });
    }

    public function down(): void
    {
        //
    }
};
