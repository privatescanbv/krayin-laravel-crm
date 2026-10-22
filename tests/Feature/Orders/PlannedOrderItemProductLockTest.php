<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ResourceOrderItem;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Person;
use Webkul\Product\Models\Product;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->actingAs(makeUser(), 'user');

    $this->salesLead = SalesLead::factory()->create();
    $this->person = Person::factory()->create();
    $this->product = Product::factory()->create();
    $this->otherProduct = Product::factory()->create();
    $this->order = Order::factory()->create(['sales_lead_id' => $this->salesLead->id]);
    $this->item = OrderItem::factory()->create([
        'order_id'   => $this->order->id,
        'product_id' => $this->product->id,
        'person_id'  => $this->person->id,
    ]);
});

function updateOrderWithProduct(int $productId)
{
    return test()->postJson(route('admin.orders.update', ['id' => test()->order->id]), [
        'title'         => test()->order->title,
        'sales_lead_id' => test()->salesLead->id,
        '_method'       => 'put',
        'items'         => [
            test()->item->id => [
                'product_id'  => $productId,
                'person_id'   => test()->person->id,
                'quantity'    => 1,
                'total_price' => 10,
            ],
        ],
    ]);
}

function updateOrderItemWithProduct(int $productId)
{
    return test()->putJson(route('admin.order_items.update', test()->item->id), [
        'order_id'    => test()->order->id,
        'product_id'  => $productId,
        'person_id'   => test()->person->id,
        'quantity'    => 1,
        'total_price' => 10,
        'status'      => 'new',
    ]);
}

test('edit order rejects product change on a planned order item', function () {
    ResourceOrderItem::factory()->create(['orderitem_id' => $this->item->id]);

    updateOrderWithProduct($this->otherProduct->id)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(["items.{$this->item->id}.product_id"]);

    expect($this->item->fresh()->product_id)->toBe($this->product->id);
});

test('edit order allows other changes on a planned order item when product is unchanged', function () {
    ResourceOrderItem::factory()->create(['orderitem_id' => $this->item->id]);

    updateOrderWithProduct($this->product->id)->assertOk();
});

test('edit order allows product change on an unplanned order item', function () {
    updateOrderWithProduct($this->otherProduct->id)->assertOk();

    expect($this->item->fresh()->product_id)->toBe($this->otherProduct->id);
});

test('edit order item rejects product change when planned', function () {
    ResourceOrderItem::factory()->create(['orderitem_id' => $this->item->id]);

    updateOrderItemWithProduct($this->otherProduct->id)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['product_id']);

    expect($this->item->fresh()->product_id)->toBe($this->product->id);
});

test('edit order item allows product change when not planned', function () {
    updateOrderItemWithProduct($this->otherProduct->id)->assertSessionHasNoErrors();

    expect($this->item->fresh()->product_id)->toBe($this->otherProduct->id);
});
