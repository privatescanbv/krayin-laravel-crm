<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderItemStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Product\Models\Product;

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);
    $this->actingAs(makeUser(), 'user');
});

test('afletteren tab does not show order items with status LOST', function () {
    $order = Order::factory()->create();
    $product = Product::factory()->create(['name' => 'TestProductVerloren_'.uniqid()]);

    $pp = PartnerProduct::factory()->create(['product_id' => $product->id]);
    $pp->purchasePrice->update(['purchase_price_clinic' => 99.00, 'purchase_price' => 99.00]);

    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'status'     => OrderItemStatus::LOST,
    ]);

    $response = $this->get(route('admin.orders.view', $order->id));
    $response->assertOk();

    $html = $response->getContent();
    $afletterenStart = strpos($html, 'leadDetailSection === \'afletteren\'');
    $afletterenEnd = strpos($html, 'leadDetailSection === \'betalingen\'');
    $afletterenSection = substr($html, $afletterenStart, $afletterenEnd - $afletterenStart);

    expect($afletterenSection)->not->toContain($product->name);
});

test('afletteren tab shows order items that are not LOST when they have purchase prices', function () {
    $order = Order::factory()->create();
    $product = Product::factory()->create(['name' => 'TestProductWon_'.uniqid()]);

    $pp = PartnerProduct::factory()->create(['product_id' => $product->id]);
    $pp->purchasePrice->update(['purchase_price_clinic' => 99.00, 'purchase_price' => 99.00]);

    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'status'     => OrderItemStatus::WON,
    ]);

    $response = $this->get(route('admin.orders.view', $order->id));
    $response->assertOk();
    $response->assertSee($product->name, false);
});
