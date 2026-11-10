<?php

namespace App\Services;

use App\Enums\PurchasePriceType;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\PurchasePrice;
use Illuminate\Support\Facades\Log;

class OrderItemPurchasePriceService
{
    public function __construct(
        private readonly OrderCheckService $orderCheckService,
    ) {}

    public function linkPartnerProduct(OrderItem $orderItem, PartnerProduct $partnerProduct): void
    {
        $orderItem->update(['partner_product_id' => $partnerProduct->id]);

        $orderItem->unsetRelation('purchasePrice');

        if ($orderItem->hasEnteredPurchasePrice()) {
            $this->orderCheckService->clearPurchasePriceIssue($orderItem);

            return;
        }

        $partnerPrice = $partnerProduct->relationLoaded('purchasePrice')
            ? $partnerProduct->purchasePrice
            : $partnerProduct->purchasePrice()->first();

        if ($partnerPrice === null) {
            $this->orderCheckService->flagPurchasePriceIssue(
                $orderItem,
                "partnerproduct \"{$partnerProduct->name}\" heeft geen inkoopprijs geconfigureerd"
            );

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

        $this->orderCheckService->clearPurchasePriceIssue($orderItem);
    }

    /**
     * Re-resolve the partner product for this order item, e.g. after its product changed.
     */
    public function relinkPartnerProduct(OrderItem $orderItem): void
    {
        $partnerProduct = $orderItem->isPlannable()
            ? $this->resolvePlannedPartnerProduct($orderItem)
            : $this->resolveNonPlannablePartnerProduct($orderItem);

        $previous = $orderItem->partnerProduct()->withTrashed()->with('purchasePrice')->first();

        // Price copied from the previous partner product is stale; a manually entered price is kept.
        if ($previous && $previous->id !== $partnerProduct?->id && $this->hasPriceCopiedFrom($orderItem, $previous)) {
            $orderItem->purchasePrice()->delete();
        }

        $partnerProduct
            ? $this->linkPartnerProduct($orderItem, $partnerProduct)
            : $this->clearPartnerProductLink($orderItem);
    }

    public function clearPartnerProductLink(OrderItem $orderItem): void
    {
        if ($orderItem->partner_product_id === null) {
            return;
        }

        $orderItem->update(['partner_product_id' => null]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveFromRequest(OrderItem $orderItem, array $data): void
    {
        $fields = PurchasePrice::priceableFieldNames();
        $total = 0.0;

        foreach ($fields as $field) {
            $total += (float) ($data[$field] ?? 0);
        }

        if ($total <= 0) {
            $orderItem->purchasePrice()->delete();

            return;
        }

        $payload = ['type' => PurchasePriceType::MAIN, 'purchase_price' => $total];

        foreach ($fields as $field) {
            $payload[$field] = (float) ($data[$field] ?? 0);
        }

        $orderItem->purchasePrice()->updateOrCreate([], $payload);

        $this->orderCheckService->clearPurchasePriceIssue($orderItem);
    }

    /**
     * Plannable products are resolved per clinic, taken from the resource the order item is
     * booked onto (see ResourceOrderItemObserver). Before planning, there is no clinic yet.
     */
    private function resolvePlannedPartnerProduct(OrderItem $orderItem): ?PartnerProduct
    {
        $clinicId = $orderItem->resourceOrderItem()->with('resource.clinicDepartment')->first()
            ?->resource
            ?->clinicDepartment
            ?->clinic_id;

        return $clinicId && $orderItem->product_id
            ? PartnerProduct::forClinicAndProduct($clinicId, $orderItem->product_id)->with('purchasePrice')->first()
            : null;
    }

    /**
     * Non-plannable products are never booked onto a resource, so there is no clinic to key
     * the partner product on (MBS-515). Resolve it straight from product_id instead; this only
     * works when exactly one active partner product matches. Having none is normal (plenty of
     * products have no partner/purchase arrangement at all), but having more than one is a real
     * configuration ambiguity, so that case is surfaced to the user via an order check.
     */
    private function resolveNonPlannablePartnerProduct(OrderItem $orderItem): ?PartnerProduct
    {
        if (! $orderItem->product_id) {
            $this->orderCheckService->clearPurchasePriceIssue($orderItem);

            return null;
        }

        $partnerProducts = PartnerProduct::bookable()
            ->where('product_id', $orderItem->product_id)
            ->with('purchasePrice')
            ->get();

        if ($partnerProducts->count() === 1) {
            return $partnerProducts->first();
        }

        if ($partnerProducts->isEmpty()) {
            $this->orderCheckService->clearPurchasePriceIssue($orderItem);

            return null;
        }

        // A valid (e.g. manually entered) price makes the ambiguity harmless: nothing is missing.
        $orderItem->unsetRelation('purchasePrice');

        if ($orderItem->hasEnteredPurchasePrice()) {
            $this->orderCheckService->clearPurchasePriceIssue($orderItem);

            return null;
        }

        $productName = $orderItem->product?->name ?? "product #{$orderItem->product_id}";
        $reason = "meerdere partnerproducten gevonden voor \"{$productName}\", vul de inkoopprijs handmatig in";

        Log::warning("[OrderItemPurchasePriceService] Kan inkoopprijs niet automatisch bepalen voor orderregel #{$orderItem->id}: {$reason}");

        $this->orderCheckService->flagPurchasePriceIssue($orderItem, $reason);

        return null;
    }

    private function hasPriceCopiedFrom(OrderItem $orderItem, PartnerProduct $partnerProduct): bool
    {
        $itemPrice = $orderItem->purchasePrice()->first();
        $partnerPrice = $partnerProduct->purchasePrice;

        if ($itemPrice === null || $partnerPrice === null) {
            return false;
        }

        return collect(['purchase_price', ...PurchasePrice::priceableFieldNames()])
            ->every(fn (string $field) => (float) $itemPrice->{$field} === (float) $partnerPrice->{$field});
    }
}
