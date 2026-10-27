<?php

namespace App\Services;

use App\Enums\PurchasePriceType;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\PurchasePrice;

class OrderItemPurchasePriceService
{
    public function linkPartnerProduct(OrderItem $orderItem, PartnerProduct $partnerProduct): void
    {
        $orderItem->update(['partner_product_id' => $partnerProduct->id]);

        $orderItem->unsetRelation('purchasePrice');

        if ($orderItem->hasEnteredPurchasePrice()) {
            return;
        }

        $partnerPrice = $partnerProduct->relationLoaded('purchasePrice')
            ? $partnerProduct->purchasePrice
            : $partnerProduct->purchasePrice()->first();

        if ($partnerPrice === null) {
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
     * Re-resolve the partner product for the planned clinic, e.g. after the order item's product changed.
     */
    public function relinkPartnerProduct(OrderItem $orderItem): void
    {
        $clinicId = $orderItem->resourceOrderItem()->with('resource.clinicDepartment')->first()
            ?->resource
            ?->clinicDepartment
            ?->clinic_id;

        $partnerProduct = $clinicId && $orderItem->product_id
            ? PartnerProduct::forClinicAndProduct($clinicId, $orderItem->product_id)->with('purchasePrice')->first()
            : null;

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
