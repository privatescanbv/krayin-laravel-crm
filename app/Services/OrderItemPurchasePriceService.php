<?php

namespace App\Services;

use App\Enums\PurchasePriceType;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\PurchasePrice;

class OrderItemPurchasePriceService
{
    public function linkPartnerProduct(OrderItem $orderItem, PartnerProduct $partnerProduct, bool $force = false): void
    {
        // Quiet: avoids re-entering OrderItemObserver::updated() while it may still be handling
        // the triggering save (e.g. a product_id change), which would otherwise recurse forever
        // because the outer save hasn't synced its original attributes yet.
        $orderItem->updateQuietly(['partner_product_id' => $partnerProduct->id]);

        $orderItem->unsetRelation('purchasePrice');

        if (! $force && $orderItem->hasEnteredPurchasePrice()) {
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

    public function clearPartnerProductLink(OrderItem $orderItem): void
    {
        if ($orderItem->partner_product_id === null) {
            return;
        }

        $orderItem->updateQuietly(['partner_product_id' => null]);
    }

    /**
     * Re-resolve the partner-product link (and MAIN purchase price) for an order item that is
     * already booked, after its product changed. Without this, an order item keeps the
     * partner_product_id/purchase price copied from its *previous* product (set when the
     * booking was first created), so the old test's purchase price silently lingers while the
     * sale price already reflects the new product.
     */
    public function resyncPartnerProductForCurrentProduct(OrderItem $orderItem): void
    {
        $resourceOrderItem = $orderItem->relationLoaded('resourceOrderItem')
            ? $orderItem->resourceOrderItem
            : $orderItem->resourceOrderItem()->with('resource.clinicDepartment')->first();

        if ($resourceOrderItem === null) {
            // Not booked to a resource: no clinic context to resync the partner link against.
            return;
        }

        $clinicId = $resourceOrderItem->resource?->clinicDepartment?->clinic_id;

        if ($clinicId === null) {
            return;
        }

        $partnerProduct = PartnerProduct::forClinicAndProduct($clinicId, $orderItem->product_id)
            ->with('purchasePrice')
            ->first();

        if ($partnerProduct === null) {
            $this->clearPartnerProductLink($orderItem);

            return;
        }

        $this->linkPartnerProduct($orderItem, $partnerProduct, force: true);
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
}
