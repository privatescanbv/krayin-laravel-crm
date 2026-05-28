<?php

namespace Tests\Unit;

use App\Enums\OrderItemStatus;
use App\Enums\OrderPurchaseStatus;
use App\Enums\PurchasePriceType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Product\Models\Product;

uses(RefreshDatabase::class);

function setPurchasePrice(PartnerProduct $pp, float $amount): void
{
    $pp->purchasePrice->update([
        'purchase_price_misc'       => $amount,
        'purchase_price_doctor'     => 0,
        'purchase_price_cardiology' => 0,
        'purchase_price_clinic'     => 0,
        'purchase_price_radiology'  => 0,
        'purchase_price'            => $amount,
    ]);
}

test('totalPurchasePrice excludes LOST order items', function () {
    $order = Order::factory()->create();
    $product = Product::factory()->create();
    setPurchasePrice(PartnerProduct::factory()->create(['product_id' => $product->id]), 100.00);

    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'status'     => OrderItemStatus::NEW->value,
    ]);

    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'status'     => OrderItemStatus::LOST->value,
    ]);

    expect($order->fresh()->totalPurchasePrice())->toBe(100.0);
});

test('totalPurchasePrice returns 0 when all order items are LOST', function () {
    $order = Order::factory()->create();
    $product = Product::factory()->create();
    setPurchasePrice(PartnerProduct::factory()->create(['product_id' => $product->id]), 200.00);

    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'status'     => OrderItemStatus::LOST->value,
    ]);

    expect($order->fresh()->totalPurchasePrice())->toBe(0.0);
});

test('totalPurchasePrice sums only non-LOST items', function () {
    $order = Order::factory()->create();
    $product = Product::factory()->create();
    setPurchasePrice(PartnerProduct::factory()->create(['product_id' => $product->id]), 50.00);

    OrderItem::factory()->count(2)->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'status'     => OrderItemStatus::PLANNED->value,
    ]);

    OrderItem::factory()->count(3)->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'status'     => OrderItemStatus::LOST->value,
    ]);

    expect($order->fresh()->totalPurchasePrice())->toBe(100.0);
});

test('purchaseStatus invoiceTotal excludes LOST order items', function () {
    $order = Order::factory()->create();
    $product = Product::factory()->create();
    setPurchasePrice(PartnerProduct::factory()->create(['product_id' => $product->id]), 80.00);

    $activeItem = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'status'     => OrderItemStatus::NEW->value,
    ]);
    $activeItem->purchasePrice()->create([
        'type'                 => PurchasePriceType::INVOICE,
        'purchase_price_misc'  => 80.00,
        'purchase_price'       => 80.00,
    ]);

    $lostItem = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'status'     => OrderItemStatus::LOST->value,
    ]);
    $lostItem->purchasePrice()->create([
        'type'                => PurchasePriceType::INVOICE,
        'purchase_price_misc' => 500.00,
        'purchase_price'      => 500.00,
    ]);

    expect($order->fresh()->purchaseStatus())->toBe(OrderPurchaseStatus::FULLY_RECEIVED);
});
