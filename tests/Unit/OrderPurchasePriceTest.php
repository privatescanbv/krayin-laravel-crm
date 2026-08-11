<?php

namespace Tests\Unit;

use App\Enums\OrderItemStatus;
use App\Enums\OrderPurchaseStatus;
use App\Enums\PurchasePriceType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Webkul\Product\Models\Product;

uses(RefreshDatabase::class);

function setOrderItemPurchasePrice(OrderItem $item, float $amount): void
{
    $item->purchasePrice()->updateOrCreate([], [
        'type'                      => PurchasePriceType::MAIN,
        'purchase_price_misc'       => $amount,
        'purchase_price_doctor'     => 0,
        'purchase_price_cardiology' => 0,
        'purchase_price_clinic'     => 0,
        'purchase_price_radiology'  => 0,
        'purchase_price'            => $amount,
    ]);
}

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

    $activeItem = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'status'     => OrderItemStatus::NEW->value,
    ]);
    setOrderItemPurchasePrice($activeItem, 100.00);

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

    OrderItem::factory()->count(2)->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'status'     => OrderItemStatus::PLANNED->value,
    ])->each(fn (OrderItem $item) => setOrderItemPurchasePrice($item, 50.00));

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

    $activeItem = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'status'     => OrderItemStatus::NEW->value,
    ]);
    setOrderItemPurchasePrice($activeItem, 80.00);
    $activeItem->invoicePurchasePrice()->create([
        'type'                 => PurchasePriceType::INVOICE,
        'purchase_price_misc'  => 80.00,
        'purchase_price'       => 80.00,
    ]);

    $lostItem = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'status'     => OrderItemStatus::LOST->value,
    ]);
    $lostItem->invoicePurchasePrice()->create([
        'type'                => PurchasePriceType::INVOICE,
        'purchase_price_misc' => 500.00,
        'purchase_price'      => 500.00,
    ]);

    expect($order->fresh()->purchaseStatus())->toBe(OrderPurchaseStatus::FULLY_RECEIVED);
});

test('PurchasePrice saving validates that total equals sum of components', function () {
    $pp = PartnerProduct::factory()->create();

    expect(fn () => $pp->purchasePrice->update([
        'purchase_price_misc'       => 100.00,
        'purchase_price_doctor'     => 0,
        'purchase_price_cardiology' => 0,
        'purchase_price_clinic'     => 0,
        'purchase_price_radiology'  => 0,
        'purchase_price'            => 999.00, // wrong total
    ]))->toThrow(InvalidArgumentException::class);
});

test('PurchasePrice saving accepts matching total', function () {
    $pp = PartnerProduct::factory()->create();

    $pp->purchasePrice->update([
        'purchase_price_misc'       => 50.00,
        'purchase_price_doctor'     => 25.00,
        'purchase_price_cardiology' => 15.00,
        'purchase_price_clinic'     => 5.00,
        'purchase_price_radiology'  => 5.00,
        'purchase_price'            => 100.00,
    ]);

    expect((float) $pp->purchasePrice->fresh()->purchase_price)->toBe(100.0);
});

test('PurchasePrice saving allows null total without validation', function () {
    $pp = PartnerProduct::factory()->create();

    $pp->purchasePrice->update([
        'purchase_price_misc'       => 50.00,
        'purchase_price_doctor'     => 0,
        'purchase_price_cardiology' => 0,
        'purchase_price_clinic'     => 0,
        'purchase_price_radiology'  => 0,
        'purchase_price'            => null,
    ]);

    expect($pp->purchasePrice->fresh()->purchase_price)->toBeNull();
});
