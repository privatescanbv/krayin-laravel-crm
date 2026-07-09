<?php

namespace Tests\Unit;

use App\Enums\PurchasePriceType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use Webkul\Product\Models\Product;

beforeEach(function () {
    $this->product = Product::factory()->create();
});

test('resolvedPurchasePrice returns all zeros when order item has no purchase price', function () {
    $order = Order::factory()->create();
    $product = Product::factory()->create();

    $item = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
    ]);

    $resolved = $item->resolvedPurchasePrice();

    expect((float) $resolved->purchase_price)->toBe(0.0)
        ->and((float) $resolved->purchase_price_misc)->toBe(0.0)
        ->and((float) $resolved->purchase_price_doctor)->toBe(0.0);
});

test('resolvedPurchasePrice does not fall back to partner product when order item has no purchase price', function () {
    $order = Order::factory()->create();
    $pp = PartnerProduct::factory()->create(['product_id' => $this->product->id]);
    $pp->purchasePrice->update([
        'purchase_price_misc'       => 10.00,
        'purchase_price_doctor'     => 25.00,
        'purchase_price_cardiology' => 15.00,
        'purchase_price_clinic'     => 20.00,
        'purchase_price_radiology'  => 30.00,
        'purchase_price'            => 100.00,
    ]);

    $item = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $this->product->id,
    ]);

    $resolved = $item->resolvedPurchasePrice();

    expect((float) $resolved->purchase_price_misc)->toBe(0.0)
        ->and((float) $resolved->purchase_price_doctor)->toBe(0.0)
        ->and((float) $resolved->purchase_price)->toBe(0.0);
});

test('resolvedPurchasePrice uses order item when it has purchase price', function () {
    $order = Order::factory()->create();
    $pp = PartnerProduct::factory()->create(['product_id' => $this->product->id]);
    $pp->purchasePrice->update([
        'purchase_price_misc'       => 10.00,
        'purchase_price_doctor'     => 10.00,
        'purchase_price_cardiology' => 10.00,
        'purchase_price_clinic'     => 10.00,
        'purchase_price_radiology'  => 10.00,
        'purchase_price'            => 50.00,
    ]);

    $item = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $this->product->id,
    ]);
    $item->purchasePrice()->create([
        'type'                       => PurchasePriceType::MAIN,
        'purchase_price_misc'        => 25.00,
        'purchase_price_doctor'      => 25.00,
        'purchase_price_cardiology'  => 25.00,
        'purchase_price_clinic'      => 25.00,
        'purchase_price_radiology'   => 25.00,
        'purchase_price'             => 125.00,
    ]);

    $resolved = $item->resolvedPurchasePrice();

    expect((float) $resolved->purchase_price_misc)->toBe(25.0)
        ->and((float) $resolved->purchase_price_doctor)->toBe(25.0)
        ->and((float) $resolved->purchase_price)->toBe(125.0);
});

test('resolvedPurchasePrice uses only order item MAIN row when present', function () {
    $order = Order::factory()->create();
    $pp = PartnerProduct::factory()->create(['product_id' => $this->product->id]);
    $pp->purchasePrice->update([
        'purchase_price_misc'       => 10.00,
        'purchase_price_doctor'     => 20.00,
        'purchase_price_cardiology' => 30.00,
        'purchase_price_clinic'     => 40.00,
        'purchase_price_radiology'  => 50.00,
        'purchase_price'            => 150.00,
    ]);

    $item = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $this->product->id,
    ]);
    $item->purchasePrice()->create([
        'type'                  => PurchasePriceType::MAIN,
        'purchase_price_doctor' => 99.00,
        'purchase_price'        => 99.00,
    ]);

    $resolved = $item->resolvedPurchasePrice();

    expect((float) $resolved->purchase_price_misc)->toBe(0.0)
        ->and((float) $resolved->purchase_price_doctor)->toBe(99.0)
        ->and((float) $resolved->purchase_price_cardiology)->toBe(0.0)
        ->and((float) $resolved->purchase_price_clinic)->toBe(0.0)
        ->and((float) $resolved->purchase_price_radiology)->toBe(0.0)
        ->and((float) $resolved->purchase_price)->toBe(99.0);
});

test('resolvedPurchasePrice does not sum multiple partner products', function () {
    $order = Order::factory()->create();
    $pp1 = PartnerProduct::factory()->create(['product_id' => $this->product->id]);
    $pp1->purchasePrice->update([
        'purchase_price_misc'       => 10.00,
        'purchase_price_doctor'     => 0,
        'purchase_price_cardiology' => 0,
        'purchase_price_clinic'     => 0,
        'purchase_price_radiology'  => 0,
        'purchase_price'            => 10.00,
    ]);
    $pp2 = PartnerProduct::factory()->create(['product_id' => $this->product->id]);
    $pp2->purchasePrice->update([
        'purchase_price_misc'       => 5.00,
        'purchase_price_doctor'     => 0,
        'purchase_price_cardiology' => 0,
        'purchase_price_clinic'     => 0,
        'purchase_price_radiology'  => 0,
        'purchase_price'            => 5.00,
    ]);

    $item = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $this->product->id,
    ]);

    $resolved = $item->resolvedPurchasePrice();

    expect((float) $resolved->purchase_price_misc)->toBe(0.0)
        ->and((float) $resolved->purchase_price)->toBe(0.0);
});

test('hasEnteredPurchasePrice is false when no MAIN row exists', function () {
    $item = OrderItem::factory()->create(['product_id' => $this->product->id]);

    expect($item->hasEnteredPurchasePrice())->toBeFalse();
});

test('hasEnteredPurchasePrice is true when MAIN row has positive total', function () {
    $item = OrderItem::factory()->create(['product_id' => $this->product->id]);
    $item->purchasePrice()->create([
        'type'                => PurchasePriceType::MAIN,
        'purchase_price_misc' => 10.00,
        'purchase_price'      => 10.00,
    ]);

    expect($item->fresh()->hasEnteredPurchasePrice())->toBeTrue();
});

test('hasEnteredPurchasePrice is false when MAIN row is all zeros', function () {
    $item = OrderItem::factory()->create(['product_id' => $this->product->id]);
    $item->purchasePrice()->create([
        'type'                       => PurchasePriceType::MAIN,
        'purchase_price_misc'        => 0,
        'purchase_price_doctor'      => 0,
        'purchase_price_cardiology'  => 0,
        'purchase_price_clinic'      => 0,
        'purchase_price_radiology'   => 0,
        'purchase_price'             => 0,
    ]);

    expect($item->fresh()->hasEnteredPurchasePrice())->toBeFalse();
});
