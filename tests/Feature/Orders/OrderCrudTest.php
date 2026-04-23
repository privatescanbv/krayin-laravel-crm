<?php

namespace Tests\Feature\Settings;

use App\Enums\OrderItemStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Product\Models\Product;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    $this->seed(TestSeeder::class);

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

test('can update order clearing user_id when select submits empty string', function () {
    $assignee = makeUser();
    $salesLead = SalesLead::factory()->create();
    $order = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
        'user_id'       => $assignee->id,
    ]);

    $payload = [
        'title'         => $order->title,
        'total_price'   => $order->total_price,
        'sales_lead_id' => $salesLead->id,
        'user_id'       => '',
        '_method'       => 'put',
    ];

    $response = $this->postJson(route('admin.orders.update', ['id' => $order->id]), $payload);
    $response->assertOk();

    expect($order->fresh()->user_id)->toBeNull();
});

test('can update order invoice_number and is_business', function () {
    $order = Order::factory()->create([
        'invoice_number' => null,
        'is_business'    => false,
    ]);
    $salesLead = SalesLead::factory()->create();
    $org = Organization::factory()->create();

    $payload = [
        'title'           => $order->title,
        'total_price'     => $order->total_price,
        'sales_lead_id'   => $salesLead->id,
        'invoice_number'  => 'INV-2026-42',
        'is_business'     => true,
        'organization_id' => $org->id,
        '_method'         => 'put',
    ];

    $response = $this->postJson(route('admin.orders.update', ['id' => $order->id]), $payload);
    $response->assertOk();

    $order->refresh();
    expect($order->invoice_number)->toBe('INV-2026-42');
    expect($order->is_business)->toBeTrue();
    expect($order->organization_id)->toBe($org->id);
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
    $person = Person::factory()->create();
    $product = Product::factory()->create(['price' => 99.50]);

    $payload = [
        'title'         => 'Test Order',
        'total_price'   => 0,
        'sales_lead_id' => $salesLead->id,
        'items'         => [
            [
                'product_id' => $product->id,
                'person_id'  => $person->id,
                'quantity'   => 3,
                // total_price omitted → should fall back to product price
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
    $person = Person::factory()->create();
    $product = Product::factory()->create(['price' => 150.75]);

    $payload = [
        'title'         => 'Updated Order',
        'total_price'   => 0,
        'sales_lead_id' => $salesLead->id,
        '_method'       => 'put',
        'items'         => [
            [
                'product_id' => $product->id,
                'person_id'  => $person->id,
                'quantity'   => 2,
                // total_price omitted → should fall back to product price
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

test('order item total_price of zero is preserved as free (not overridden by product price)', function () {
    $salesLead = SalesLead::factory()->create();
    $person = Person::factory()->create();
    $product = Product::factory()->create(['price' => 99.50]);

    $payload = [
        'title'         => 'Free Order',
        'total_price'   => 0,
        'sales_lead_id' => $salesLead->id,
        'items'         => [
            [
                'product_id'  => $product->id,
                'person_id'   => $person->id,
                'quantity'    => 2,
                'total_price' => 0, // Explicit free price — must not be overridden
            ],
        ],
    ];

    $response = $this->postJson(route('admin.orders.store'), $payload);
    $response->assertOk();

    $this->assertDatabaseHas('order_items', [
        'product_id'  => $product->id,
        'quantity'    => 2,
        'total_price' => 0,
    ]);
});

test('removing an order item from the update payload sets its status to lost instead of deleting', function () {
    $order = Order::factory()->create();
    $salesLead = SalesLead::factory()->create();
    $personA = Person::factory()->create();
    $personB = Person::factory()->create();
    $product = Product::factory()->create(['price' => 40.00]);

    $keptItem = OrderItem::factory()->create([
        'order_id'    => $order->id,
        'product_id'  => $product->id,
        'person_id'   => $personA->id,
        'quantity'    => 1,
        'total_price' => 40.00,
        'status'      => OrderItemStatus::NEW->value,
    ]);
    $removedItem = OrderItem::factory()->create([
        'order_id'    => $order->id,
        'product_id'  => $product->id,
        'person_id'   => $personB->id,
        'quantity'    => 1,
        'total_price' => 40.00,
        'status'      => OrderItemStatus::NEW->value,
    ]);

    $payload = [
        'title'         => 'Updated Order',
        'total_price'   => 0,
        'sales_lead_id' => $salesLead->id,
        '_method'       => 'put',
        'items'         => [
            $keptItem->id => [
                'product_id'  => $product->id,
                'person_id'   => $personA->id,
                'quantity'    => 1,
                'total_price' => 40.00,
            ],
        ],
    ];

    $response = $this->postJson(route('admin.orders.update', ['id' => $order->id]), $payload);
    $response->assertOk();

    expect($removedItem->fresh()->status)->toBe(OrderItemStatus::LOST);
    expect($keptItem->fresh()->status)->toBe(OrderItemStatus::NEW);
    $this->assertDatabaseHas('order_items', ['id' => $removedItem->id]);
});

test('order item total_price uses provided value when not zero', function () {
    $salesLead = SalesLead::factory()->create();
    $person = Person::factory()->create();
    $product = Product::factory()->create(['price' => 100.00]);

    $payload = [
        'title'         => 'Test Order',
        'total_price'   => 0,
        'sales_lead_id' => $salesLead->id,
        'items'         => [
            [
                'product_id'  => $product->id,
                'person_id'   => $person->id,
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

test('order create form prefills title from sales lead name when sales_lead_id is set', function () {
    $salesLead = SalesLead::factory()->create([
        'name' => 'OrderCreateTitlePrefill2026',
    ]);

    $response = $this->get(route('admin.orders.create', ['sales_lead_id' => $salesLead->id]));

    $response->assertOk();
    $response->assertSee('OrderCreateTitlePrefill2026', false);
});

test('order create form prefills organization from lead when sales_lead_id is set', function () {
    $org = Organization::factory()->create(['name' => 'OrderCreateLeadOrg2026']);
    $lead = Lead::factory()->create(['organization_id' => $org->id]);
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);

    $response = $this->get(route('admin.orders.create', ['sales_lead_id' => $salesLead->id]));

    $response->assertOk();
    $response->assertSee('OrderCreateLeadOrg2026', false);
});

test('order create form prefills organization from linked person when lead has no organization', function () {
    $lead = Lead::factory()->create(['organization_id' => null]);
    $salesLead = SalesLead::factory()->create([
        'lead_id'            => $lead->id,
        'contact_person_id'  => null,
    ]);
    $person = Person::factory()->withOrganisation('OrderCreatePersonOrg2026')->create();
    $salesLead->persons()->attach($person->id);

    $response = $this->get(route('admin.orders.create', ['sales_lead_id' => $salesLead->id]));

    $response->assertOk();
    $response->assertSee('OrderCreatePersonOrg2026', false);
});

test('order edit form keeps zakelijk off when order is not business even if lead has organization', function () {
    $org = Organization::factory()->create(['name' => 'OrderEditLeadOrgHint2026']);
    $lead = Lead::factory()->create(['organization_id' => $org->id]);
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create([
        'sales_lead_id'   => $salesLead->id,
        'is_business'     => false,
        'organization_id' => null,
    ]);

    $response = $this->get(route('admin.orders.edit', ['id' => $order->id]));
    $response->assertOk();
    $response->assertSee(":initial-is-business='false'", false);
    $response->assertSee(":initial-org='null'", false);
    $response->assertSee('OrderEditLeadOrgHint2026', false);
});

test('order create form leaves initial org empty when lead and persons have no organization', function () {
    $lead = Lead::factory()->create(['organization_id' => null]);
    $salesLead = SalesLead::factory()->create([
        'lead_id'           => $lead->id,
        'contact_person_id' => null,
    ]);
    $person = Person::factory()->create(['organization_id' => null]);
    $salesLead->persons()->attach($person->id);

    $response = $this->get(route('admin.orders.create', ['sales_lead_id' => $salesLead->id]));

    $response->assertOk();
    $response->assertSee(":initial-org='null'", false);
});
