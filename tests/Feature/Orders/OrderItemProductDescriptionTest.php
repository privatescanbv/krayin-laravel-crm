<?php

namespace Tests\Feature\Orders;

use App\Models\Clinic;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use Webkul\Product\Models\Product;

test('getProductDescription returns order item description when present', function (): void {
    $orderItem = OrderItem::factory()->create([
        'description' => 'Order item description',
    ]);

    expect($orderItem->getProductDescription())->toBe('Order item description');
});

test('getProductDescription returns partner product description for resource clinic match', function (): void {
    $clinic = Clinic::factory()->create();
    $product = Product::factory()->create(['description' => 'Product description']);
    $orderItem = OrderItem::factory()->create([
        'product_id'   => $product->id,
        'description'  => null,
    ]);
    $resource = Resource::factory()->create(['clinic_id' => $clinic->id]);
    $partnerProduct = PartnerProduct::factory()->create([
        'product_id'   => $product->id,
        'description'  => 'Partner product description',
    ]);
    $partnerProduct->clinics()->sync([$clinic->id]);

    ResourceOrderItem::factory()->create([
        'resource_id'  => $resource->id,
        'orderitem_id' => $orderItem->id,
    ]);

    expect($orderItem->getProductDescription())->toBe('Partner product description');
});

test('getProductDescription falls back to product description without resource order item', function (): void {
    $product = Product::factory()->create(['description' => 'Product description']);
    $orderItem = OrderItem::factory()->create([
        'product_id'   => $product->id,
        'description'  => null,
    ]);

    expect($orderItem->getProductDescription())->toBe('Product description');
});

test('getProductDescription falls back to product description without matching partner product', function (): void {
    $clinic = Clinic::factory()->create();
    $otherClinic = Clinic::factory()->create();
    $product = Product::factory()->create(['description' => 'Product description']);
    $orderItem = OrderItem::factory()->create([
        'product_id'   => $product->id,
        'description'  => null,
    ]);
    $resource = Resource::factory()->create(['clinic_id' => $clinic->id]);
    $partnerProduct = PartnerProduct::factory()->create([
        'product_id'   => $product->id,
        'description'  => 'Other clinic partner product description',
    ]);
    $partnerProduct->clinics()->sync([$otherClinic->id]);

    ResourceOrderItem::factory()->create([
        'resource_id'  => $resource->id,
        'orderitem_id' => $orderItem->id,
    ]);

    expect($orderItem->getProductDescription())->toBe('Product description');
});

test('getProductDescription returns empty string when product is null', function (): void {
    $orderItem = new OrderItem(['description' => null]);

    expect($orderItem->getProductDescription())->toBe('');
});
