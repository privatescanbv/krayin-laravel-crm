<?php

namespace Tests\Feature\Settings;

use App\Models\Order;
use App\Models\SalesLead;
use Illuminate\Support\Facades\Http;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Product\Models\Product;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);

    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('orders index returns datagrid json', function () {
    $o1 = Order::factory()->create();
    $o2 = Order::factory()->create();

    $response = $this->getJson(route('admin.orders.index'));
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($o1->id, $o2->id);
});

test('can create order', function () {
    $salesLead = SalesLead::factory()->create();

    $payload = [
        'title'         => 'Test Order',
        'total_price'   => 123.45,
        'sales_lead_id' => $salesLead->id,
    ];

    $response = $this->postJson(route('admin.orders.store'), $payload);
    $response->assertOk();

    $this->assertDatabaseHas('orders', [
        'title' => 'Test Order',
    ]);
});

test('can update order', function () {
    $order = Order::factory()->create();
    $salesLead = SalesLead::factory()->create();

    $payload = [
        'title'         => 'Updated Order',
        'total_price'   => 999.99,
        'sales_lead_id' => $salesLead->id,
        '_method'       => 'put',
    ];

    $response = $this->postJson(route('admin.orders.update', ['id' => $order->id]), $payload);
    $response->assertOk()->assertJsonPath('data.title', 'Updated Order');

    $this->assertDatabaseHas('orders', [
        'id'    => $order->id,
        'title' => 'Updated Order',
    ]);
});

test('can delete order', function () {
    $order = Order::factory()->create();

    $response = $this->deleteJson(route('admin.orders.delete', ['id' => $order->id]));
    $response->assertOk();

    $this->assertDatabaseMissing('orders', [
        'id' => $order->id,
    ]);
});

test('order item total_price is automatically calculated from product price when creating order', function () {
    $salesLead = SalesLead::factory()->create();
    $product = Product::factory()->create(['price' => 99.50]);

    $payload = [
        'title'         => 'Test Order',
        'total_price'   => 0,
        'sales_lead_id' => $salesLead->id,
        'items'         => [
            [
                'product_id'  => $product->id,
                'quantity'    => 3,
                'total_price' => 0, // Should be calculated automatically
            ],
        ],
    ];

    $response = $this->postJson(route('admin.orders.store'), $payload);
    $response->assertOk();

    // Check that order item was created with calculated total_price
    $this->assertDatabaseHas('order_items', [
        'product_id'  => $product->id,
        'quantity'    => 3,
        'total_price' => 298.50, // 99.50 * 3
    ]);
});

test('order item total_price is automatically calculated from product price when updating order', function () {
    $order = Order::factory()->create();
    $salesLead = SalesLead::factory()->create();
    $product = Product::factory()->create(['price' => 150.75]);

    $payload = [
        'title'         => 'Updated Order',
        'total_price'   => 0,
        'sales_lead_id' => $salesLead->id,
        '_method'       => 'put',
        'items'         => [
            [
                'product_id'  => $product->id,
                'quantity'    => 2,
                'total_price' => 0, // Should be calculated automatically
            ],
        ],
    ];

    $response = $this->postJson(route('admin.orders.update', ['id' => $order->id]), $payload);
    $response->assertOk();

    // Check that order item was created/updated with calculated total_price
    $this->assertDatabaseHas('order_items', [
        'order_id'    => $order->id,
        'product_id'  => $product->id,
        'quantity'    => 2,
        'total_price' => 301.50, // 150.75 * 2
    ]);
});

test('order item total_price uses provided value when not zero', function () {
    $salesLead = SalesLead::factory()->create();
    $product = Product::factory()->create(['price' => 100.00]);

    $payload = [
        'title'         => 'Test Order',
        'total_price'   => 0,
        'sales_lead_id' => $salesLead->id,
        'items'         => [
            [
                'product_id'  => $product->id,
                'quantity'    => 2,
                'total_price' => 250.00, // Custom price, should not be overridden
            ],
        ],
    ];

    $response = $this->postJson(route('admin.orders.store'), $payload);
    $response->assertOk();

    // Check that order item uses the provided total_price, not calculated one
    $this->assertDatabaseHas('order_items', [
        'product_id'  => $product->id,
        'quantity'    => 2,
        'total_price' => 250.00, // Custom price, not 200.00 (100.00 * 2)
    ]);
});

test('can detach gvl form when forms api returns 200', function () {
    config([
        'services.forms.api_url'   => 'http://forms',
        'services.forms.api_token' => 'test-token',
    ]);

    $originalLink = 'https://forms.example.com/forms/123';
    $salesLead = SalesLead::factory()->create(['gvl_form_link' => $originalLink]);
    $order = Order::factory()->for($salesLead)->create();

    Http::fake([
        'http://forms/api/forms/123' => Http::response([], 200),
    ]);

    $response = $this->deleteJson(route('admin.orders.gvl-form.detach', ['orderId' => $order->id]));

    $response->assertOk()
        ->assertJsonPath('message', 'GVL formulier is ontkoppeld.');

    $this->assertDatabaseHas('salesleads', [
        'id'             => $salesLead->id,
        'gvl_form_link'  => null,
    ]);

    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE'
            && str_contains($request->url(), 'http://forms/api/forms/123')
            && ($request->header('X-API-KEY')[0] ?? null) === 'test-token';
    });
});

test('gvl form stays linked when forms api responds with error', function () {
    config([
        'services.forms.api_url'   => 'http://forms',
        'services.forms.api_token' => null,
    ]);

    $originalLink = 'https://forms.example.com/forms/456';
    $salesLead = SalesLead::factory()->create(['gvl_form_link' => $originalLink]);
    $order = Order::factory()->for($salesLead)->create();

    Http::fake([
        'http://forms/api/forms' => Http::response(['message' => 'not found'], 404),
    ]);

    $response = $this->deleteJson(route('admin.orders.gvl-form.detach', ['orderId' => $order->id]));

    $response->assertStatus(404)
        ->assertJsonPath('message', 'not found');

    $this->assertDatabaseHas('salesleads', [
        'id'            => $salesLead->id,
        'gvl_form_link' => $originalLink,
    ]);

    Http::assertSentCount(1);
});
