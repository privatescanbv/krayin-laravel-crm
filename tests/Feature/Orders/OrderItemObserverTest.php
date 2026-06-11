<?php

use App\Enums\OrderItemStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ResourceOrderItem;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Person;
use Webkul\Product\Models\Product;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

test('setting status to lost deletes all resource order items', function () {
    $orderItem = OrderItem::factory()->create(['status' => OrderItemStatus::PLANNED->value]);
    ResourceOrderItem::factory()->count(2)->create(['orderitem_id' => $orderItem->id]);

    expect(ResourceOrderItem::where('orderitem_id', $orderItem->id)->count())->toBe(2);

    $orderItem->status = OrderItemStatus::LOST->value;
    $orderItem->save();

    expect(ResourceOrderItem::where('orderitem_id', $orderItem->id)->count())->toBe(0);
});

test('setting status to lost keeps status lost not reset to new', function () {
    $orderItem = OrderItem::factory()->create(['status' => OrderItemStatus::PLANNED->value]);
    ResourceOrderItem::factory()->create(['orderitem_id' => $orderItem->id]);

    $orderItem->status = OrderItemStatus::LOST->value;
    $orderItem->save();

    expect($orderItem->fresh()->status)->toBe(OrderItemStatus::LOST);
});

test('setting status to new does not delete resource order items', function () {
    $orderItem = OrderItem::factory()->create(['status' => OrderItemStatus::PLANNED->value]);
    $roi = ResourceOrderItem::factory()->create(['orderitem_id' => $orderItem->id]);

    $orderItem->status = OrderItemStatus::NEW->value;
    $orderItem->save();

    expect(ResourceOrderItem::find($roi->id))->not->toBeNull();
});

test('resource order items from other order items are not affected', function () {
    $itemA = OrderItem::factory()->create(['status' => OrderItemStatus::PLANNED->value]);
    $itemB = OrderItem::factory()->create(['status' => OrderItemStatus::PLANNED->value]);

    ResourceOrderItem::factory()->create(['orderitem_id' => $itemA->id]);
    $roiB = ResourceOrderItem::factory()->create(['orderitem_id' => $itemB->id]);

    $itemA->status = OrderItemStatus::LOST->value;
    $itemA->save();

    expect(ResourceOrderItem::find($roiB->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// logFieldChanges audit trail
// ---------------------------------------------------------------------------

test('updating order item name creates a system activity on the parent order', function () {
    $order = Order::factory()->create();
    $orderItem = OrderItem::factory()->create(['order_id' => $order->id, 'name' => 'Oud naam']);

    $orderItem->update(['name' => 'Nieuw naam']);

    $activity = DB::table('activities')
        ->where('order_id', $order->id)
        ->where('type', 'system')
        ->where('title', 'LIKE', 'Naam gewijzigd:%')
        ->first();

    expect($activity)->not->toBeNull();

    $additional = json_decode($activity->additional, true);
    expect($additional['old']['label'])->toBe('Oud naam')
        ->and($additional['new']['label'])->toBe('Nieuw naam');
});

test('order item audit activity is written to parent order not to other orders', function () {
    $orderA = Order::factory()->create();
    $orderB = Order::factory()->create();
    $orderItem = OrderItem::factory()->create(['order_id' => $orderA->id, 'name' => 'Item A']);

    $orderItem->update(['name' => 'Item A gewijzigd']);

    expect(
        DB::table('activities')->where('order_id', $orderA->id)->where('type', 'system')->count()
    )->toBeGreaterThanOrEqual(1);

    expect(
        DB::table('activities')->where('order_id', $orderB->id)->where('type', 'system')->count()
    )->toBe(0);
});

test('updating order item product_id logs product names as labels', function () {
    $productA = Product::factory()->create(['name' => 'Product Oud']);
    $productB = Product::factory()->create(['name' => 'Product Nieuw']);
    $order = Order::factory()->create();
    $orderItem = OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $productA->id]);

    $orderItem->update(['product_id' => $productB->id]);

    $activity = DB::table('activities')
        ->where('order_id', $order->id)
        ->where('type', 'system')
        ->where('title', 'LIKE', 'Product gewijzigd:%')
        ->first();

    expect($activity)->not->toBeNull();

    $additional = json_decode($activity->additional, true);
    expect($additional['old']['label'])->toBe('Product Oud')
        ->and($additional['new']['label'])->toBe('Product Nieuw');
});

test('updating order item quantity creates a system activity', function () {
    $order = Order::factory()->create();
    $orderItem = OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 1]);

    $orderItem->update(['quantity' => 3]);

    $activity = DB::table('activities')
        ->where('order_id', $order->id)
        ->where('type', 'system')
        ->where('title', 'LIKE', 'Aantal gewijzigd:%')
        ->first();

    expect($activity)->not->toBeNull();

    $additional = json_decode($activity->additional, true);
    expect($additional['old']['label'])->toBe('1')
        ->and($additional['new']['label'])->toBe('3');
});

test('updating order item total_price logs formatted euro amounts', function () {
    $order = Order::factory()->create();
    $orderItem = OrderItem::factory()->create(['order_id' => $order->id, 'total_price' => 100.00]);

    $orderItem->update(['total_price' => 250.50]);

    $activity = DB::table('activities')
        ->where('order_id', $order->id)
        ->where('type', 'system')
        ->where('title', 'LIKE', 'Prijs gewijzigd:%')
        ->first();

    expect($activity)->not->toBeNull();

    $additional = json_decode($activity->additional, true);
    expect($additional['old']['label'])->toBe('€ 100,00')
        ->and($additional['new']['label'])->toBe('€ 250,50');
});

test('updating order item status logs status labels', function () {
    $order = Order::factory()->create();
    $orderItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::NEW->value]);

    $orderItem->update(['status' => OrderItemStatus::LOST->value]);

    $activity = DB::table('activities')
        ->where('order_id', $order->id)
        ->where('type', 'system')
        ->where('title', 'LIKE', 'Status gewijzigd:%')
        ->first();

    expect($activity)->not->toBeNull();

    $additional = json_decode($activity->additional, true);
    expect($additional['old']['label'])->toBe(OrderItemStatus::NEW->label())
        ->and($additional['new']['label'])->toBe(OrderItemStatus::LOST->label());
});

test('updating order item person_id logs person name as label', function () {
    $person = Person::factory()->create(['first_name' => 'Jan', 'last_name' => 'Jansen']);
    $order = Order::factory()->create();
    $orderItem = OrderItem::factory()->create(['order_id' => $order->id, 'person_id' => null]);

    $orderItem->update(['person_id' => $person->id]);

    $activity = DB::table('activities')
        ->where('order_id', $order->id)
        ->where('type', 'system')
        ->where('title', 'LIKE', 'Patiënt gewijzigd:%')
        ->first();

    expect($activity)->not->toBeNull();

    $additional = json_decode($activity->additional, true);
    expect($additional['new']['label'])->toBe($person->name);
});

test('order item audit title includes item reference', function () {
    $order = Order::factory()->create();
    $orderItem = OrderItem::factory()->create(['order_id' => $order->id, 'name' => 'CT Scan', 'quantity' => 1]);

    $orderItem->update(['quantity' => 5]);

    $activity = DB::table('activities')
        ->where('order_id', $order->id)
        ->where('type', 'system')
        ->where('title', 'LIKE', '%order item #'.$orderItem->id.'%')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->title)->toContain("CT Scan (order item #{$orderItem->id})");
});

test('unchanged order item fields do not create activity log entries', function () {
    $order = Order::factory()->create();
    $orderItem = OrderItem::factory()->create(['order_id' => $order->id, 'name' => 'Zelfde naam']);

    $countBefore = DB::table('activities')->where('order_id', $order->id)->where('type', 'system')
        ->where('title', 'LIKE', 'Naam gewijzigd:%')->count();

    $orderItem->update(['name' => 'Zelfde naam']);

    $countAfter = DB::table('activities')->where('order_id', $order->id)->where('type', 'system')
        ->where('title', 'LIKE', 'Naam gewijzigd:%')->count();

    expect($countAfter)->toBe($countBefore);
});
