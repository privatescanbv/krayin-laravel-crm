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

test('resolvedPurchasePrice returns all zeros when product has no partner products', function () {
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

test('resolvedPurchasePrice uses partner product when order item has no purchase price', function () {
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

    expect((float) $resolved->purchase_price_misc)->toBe(10.0)
        ->and((float) $resolved->purchase_price_doctor)->toBe(25.0)
        ->and((float) $resolved->purchase_price)->toBe(100.0);
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

test('resolvedPurchasePrice cascades: order item overrides only set fields, rest from partner product', function () {
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

    expect((float) $resolved->purchase_price_misc)->toBe(10.0)
        ->and((float) $resolved->purchase_price_doctor)->toBe(99.0)
        ->and((float) $resolved->purchase_price_cardiology)->toBe(30.0)
        ->and((float) $resolved->purchase_price_clinic)->toBe(40.0)
        ->and((float) $resolved->purchase_price_radiology)->toBe(50.0);
});

test('resolvedPurchasePrice returns zeros when all levels are null', function () {
    $order = Order::factory()->create();
    $product = Product::factory()->create();
    $pp = PartnerProduct::factory()->create(['product_id' => $product->id]);
    $pp->purchasePrice->update([
        'purchase_price_misc'       => null,
        'purchase_price_doctor'     => null,
        'purchase_price_cardiology' => null,
        'purchase_price_clinic'     => null,
        'purchase_price_radiology'  => null,
        'purchase_price'            => null,
    ]);

    $item = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
    ]);

    $resolved = $item->resolvedPurchasePrice();

    expect((float) $resolved->purchase_price)->toBe(0.0)
        ->and((float) $resolved->purchase_price_misc)->toBe(0.0);
});

test('resolvedPurchasePrice sums multiple partner products', function () {
    $order = Order::factory()->create();
    $pp1 = PartnerProduct::factory()->create(['product_id' => $this->product->id]);
    $pp1->purchasePrice->update([
        'purchase_price_misc'       => 10.00,
        'purchase_price_doctor'     => 20.00,
        'purchase_price_cardiology' => 0,
        'purchase_price_clinic'     => 0,
        'purchase_price_radiology'  => 0,
        'purchase_price'            => 30.00,
    ]);
    $pp2 = PartnerProduct::factory()->create(['product_id' => $this->product->id]);
    $pp2->purchasePrice->update([
        'purchase_price_misc'       => 5.00,
        'purchase_price_doctor'     => 15.00,
        'purchase_price_cardiology' => 0,
        'purchase_price_clinic'     => 0,
        'purchase_price_radiology'  => 0,
        'purchase_price'            => 20.00,
    ]);

    $item = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $this->product->id,
    ]);

    $resolved = $item->resolvedPurchasePrice();

    expect((float) $resolved->purchase_price_misc)->toBe(15.0)
        ->and((float) $resolved->purchase_price_doctor)->toBe(35.0)
        ->and((float) $resolved->purchase_price)->toBe(50.0);
});

test('resolvedPurchasePrice uses order item zero over explicit zero from partner product', function () {
    $order = Order::factory()->create();
    $pp = PartnerProduct::factory()->create(['product_id' => $this->product->id]);
    $pp->purchasePrice->update([
        'purchase_price_misc'       => 100.00,
        'purchase_price_doctor'     => 200.00,
        'purchase_price_cardiology' => 50.00,
        'purchase_price_clinic'     => 50.00,
        'purchase_price_radiology'  => 50.00,
        'purchase_price'            => 450.00,
    ]);

    $item = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $this->product->id,
    ]);
    $item->purchasePrice()->create([
        'type'                       => PurchasePriceType::MAIN,
        'purchase_price_misc'        => 0,
        'purchase_price_doctor'      => 0,
        'purchase_price_cardiology'  => 0,
        'purchase_price_clinic'      => 0,
        'purchase_price_radiology'   => 0,
        'purchase_price'             => 0,
    ]);

    $resolved = $item->resolvedPurchasePrice();

    expect((float) $resolved->purchase_price_misc)->toBe(0.0)
        ->and((float) $resolved->purchase_price_doctor)->toBe(0.0)
        ->and((float) $resolved->purchase_price)->toBe(0.0);
});

test('resolvedPurchasePrice returns zeros when partner products have no purchase price', function () {
    $order = Order::factory()->create();
    $pp = PartnerProduct::factory()->create(['product_id' => $this->product->id]);
    $pp->purchasePrice->delete();

    $item = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $this->product->id,
    ]);

    $resolved = $item->resolvedPurchasePrice();

    expect((float) $resolved->purchase_price)->toBe(0.0)
        ->and((float) $resolved->purchase_price_misc)->toBe(0.0);
});
