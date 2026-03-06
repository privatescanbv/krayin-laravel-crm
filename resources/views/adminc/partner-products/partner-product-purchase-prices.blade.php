@props(['partnerProduct' => null])

<x-adminc::components.purchase-price-fields
    :purchase-price="$partnerProduct->purchasePrice ?? null"
/>
