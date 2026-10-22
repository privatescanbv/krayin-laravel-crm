<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Person;
use Webkul\Product\Models\Product;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->actingAs(makeUser(), 'user');
});

test('new order items without sort_order are appended per order', function () {
    $order = Order::factory()->create();
    $otherOrder = Order::factory()->create();

    $first = OrderItem::factory()->create(['order_id' => $order->id]);
    $second = OrderItem::factory()->create(['order_id' => $order->id]);
    $other = OrderItem::factory()->create(['order_id' => $otherOrder->id]);
    $explicit = OrderItem::factory()->create(['order_id' => $order->id, 'sort_order' => 0]);

    expect($first->sort_order)->toBe(1)
        ->and($second->sort_order)->toBe(2)
        ->and($other->sort_order)->toBe(1)
        ->and($explicit->fresh()->sort_order)->toBe(0);
});

test('order item relations are sorted by sort_order then id', function () {
    $order = Order::factory()->create();

    $c = OrderItem::factory()->create(['order_id' => $order->id, 'sort_order' => 3]);
    $a = OrderItem::factory()->create(['order_id' => $order->id, 'sort_order' => 1]);
    $b1 = OrderItem::factory()->create(['order_id' => $order->id, 'sort_order' => 2]);
    $b2 = OrderItem::factory()->create(['order_id' => $order->id, 'sort_order' => 2]);

    $expected = [$a->id, $b1->id, $b2->id, $c->id];

    expect($order->orderItems()->pluck('id')->all())->toBe($expected)
        ->and($order->activeOrderItems()->pluck('id')->all())->toBe($expected)
        ->and(Order::with('orderItems')->find($order->id)->orderItems->pluck('id')->all())->toBe($expected);
});

test('order update saves sort_order from the form, duplicates allowed', function () {
    $salesLead = SalesLead::factory()->create();
    $person = Person::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    $one = OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'person_id' => $person->id]);
    $two = OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'person_id' => $person->id]);

    $item = fn (int $sortOrder) => [
        'product_id'  => $product->id,
        'person_id'   => $person->id,
        'quantity'    => 1,
        'total_price' => 10,
        'sort_order'  => $sortOrder,
    ];

    $this->postJson(route('admin.orders.update', ['id' => $order->id]), [
        'title'         => $order->title,
        'sales_lead_id' => $salesLead->id,
        '_method'       => 'put',
        'items'         => [
            $one->id    => $item(5),
            $two->id    => $item(5),
            'item_1'    => $item(1),
        ],
    ])->assertOk();

    $new = OrderItem::where('order_id', $order->id)->whereNotIn('id', [$one->id, $two->id])->sole();

    expect($one->fresh()->sort_order)->toBe(5)
        ->and($two->fresh()->sort_order)->toBe(5)
        ->and($new->sort_order)->toBe(1)
        ->and($order->orderItems()->pluck('id')->all())->toBe([$new->id, $one->id, $two->id]);
});

test('order update without sort_order keeps the existing number', function () {
    $salesLead = SalesLead::factory()->create();
    $person = Person::factory()->create();
    $product = Product::factory()->create();
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);
    $existing = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'person_id'  => $person->id,
        'sort_order' => 7,
    ]);

    $this->postJson(route('admin.orders.update', ['id' => $order->id]), [
        'title'         => $order->title,
        'sales_lead_id' => $salesLead->id,
        '_method'       => 'put',
        'items'         => [
            $existing->id => ['product_id' => $product->id, 'person_id' => $person->id, 'quantity' => 2, 'total_price' => 10],
        ],
    ])->assertOk();

    expect($existing->fresh()->sort_order)->toBe(7);
});
