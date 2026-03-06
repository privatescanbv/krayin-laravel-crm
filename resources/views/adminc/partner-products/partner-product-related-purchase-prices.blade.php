@props(['partnerProduct' => null])

<x-adminc::components.purchase-price-fields
    :purchase-price="$partnerProduct->relatedPurchasePrice ?? null"
    field-prefix="rel_"
    title="admin::app.partner_products.index.create.related_purchase_prices"
    total-label="admin::app.partner_products.index.create.rel_purchase_price_total"
    total-id="rel-purchase-price-total"
/>
