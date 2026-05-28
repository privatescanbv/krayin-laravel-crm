<?php

use App\Enums\OrderItemStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webkul\Product\Models\Product;

uses(RefreshDatabase::class);

// Helper: create a ResourceOrderItem with a specific datetime
function slotAt(OrderItem $item, string $datetime): ResourceOrderItem
{
    return ResourceOrderItem::factory()->create([
        'orderitem_id' => $item->id,
        'from'         => Carbon::parse($datetime),
        'to'           => Carbon::parse($datetime)->addHour(),
    ]);
}

test('clinicGuideDays returns empty when no first examination and no slots', function () {
    $order = Order::factory()->create(['first_examination_at' => null]);

    expect($order->clinicGuideDays())->toBeEmpty();
});

test('clinicGuideDays returns single entry when only first_examination_at is set', function () {
    $order = Order::factory()->create([
        'first_examination_at'   => '2026-06-10',
        'first_examination_time' => '09:00',
    ]);

    $days = $order->clinicGuideDays();

    expect($days)->toHaveCount(1);
    expect($days[0]['date']->toDateString())->toBe('2026-06-10');
    expect($days[0]['at']->format('H:i'))->toBe('09:00');
});

test('clinicGuideDays uses first_examination_at date and first_examination_time for day 1', function () {
    $order = Order::factory()->create([
        'first_examination_at'   => '2026-06-10',
        'first_examination_time' => '08:30',
    ]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'status'   => OrderItemStatus::PLANNED->value,
    ]);
    slotAt($item, '2026-06-10 10:00:00'); // slot on same day — should not override day 1 time

    $days = $order->fresh()->clinicGuideDays();

    expect($days)->toHaveCount(1);
    expect($days[0]['at']->format('H:i'))->toBe('08:30');
});

test('clinicGuideDays derives day 1 from earliest slot when no first_examination_at', function () {
    $order = Order::factory()->create(['first_examination_at' => null]);
    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'status'   => OrderItemStatus::PLANNED->value,
    ]);

    slotAt($item, '2026-06-10 09:00:00');
    slotAt($item, '2026-06-10 11:00:00');

    $days = $order->fresh()->clinicGuideDays();

    expect($days)->toHaveCount(1);
    expect($days[0]['date']->toDateString())->toBe('2026-06-10');
    expect($days[0]['at']->format('H:i'))->toBe('09:00');
});

test('clinicGuideDays returns two entries for slots on two different days', function () {
    $order = Order::factory()->create([
        'first_examination_at'   => '2026-06-10',
        'first_examination_time' => '09:00',
    ]);
    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'status'   => OrderItemStatus::PLANNED->value,
    ]);

    slotAt($item, '2026-06-11 08:00:00'); // day 2

    $days = $order->fresh()->clinicGuideDays();

    expect($days)->toHaveCount(2);
    expect($days[0]['date']->toDateString())->toBe('2026-06-10');
    expect($days[1]['date']->toDateString())->toBe('2026-06-11');
    expect($days[1]['at']->format('H:i'))->toBe('08:00');
});

test('clinicGuideDays later-day time is earliest slot on that day', function () {
    $order = Order::factory()->create([
        'first_examination_at'   => '2026-06-10',
        'first_examination_time' => '09:00',
    ]);
    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'status'   => OrderItemStatus::PLANNED->value,
    ]);

    slotAt($item, '2026-06-11 14:00:00');
    slotAt($item, '2026-06-11 10:30:00');
    slotAt($item, '2026-06-11 12:00:00');

    $days = $order->fresh()->clinicGuideDays();

    expect($days)->toHaveCount(2);
    expect($days[1]['at']->format('H:i'))->toBe('10:30');
});

test('clinicGuideDays returns three entries for slots on three different days', function () {
    $order = Order::factory()->create(['first_examination_at' => null]);
    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'status'   => OrderItemStatus::PLANNED->value,
    ]);

    slotAt($item, '2026-06-10 09:00:00');
    slotAt($item, '2026-06-11 08:00:00');
    slotAt($item, '2026-06-12 07:00:00');

    $days = $order->fresh()->clinicGuideDays();

    expect($days)->toHaveCount(3);
    expect($days[0]['date']->toDateString())->toBe('2026-06-10');
    expect($days[1]['date']->toDateString())->toBe('2026-06-11');
    expect($days[2]['date']->toDateString())->toBe('2026-06-12');
});

test('clinicGuideDays excludes LOST order items from multi-day calculation', function () {
    // In production, LOST items never have slots (observer deletes them on status change).
    // This test verifies the defensive filter in clinicGuideDays() by inserting directly
    // into the DB to bypass all observers — reproducing the invariant we want to protect.
    $order = Order::factory()->create([
        'first_examination_at'   => '2026-06-10',
        'first_examination_time' => '09:00',
    ]);

    $activeItem = OrderItem::factory()->create([
        'order_id' => $order->id,
        'status'   => OrderItemStatus::PLANNED->value,
    ]);
    slotAt($activeItem, '2026-06-11 09:00:00');

    // Insert a LOST item with a slot directly — bypasses all observers
    $lostItemId = DB::table('order_items')->insertGetId([
        'order_id'    => $order->id,
        'product_id'  => Product::factory()->create()->id,
        'quantity'    => 1,
        'total_price' => 0,
        'status'      => OrderItemStatus::LOST->value,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
    $resource = Resource::factory()->create();
    DB::table('resource_orderitem')->insert([
        'resource_id'  => $resource->id,
        'orderitem_id' => $lostItemId,
        'from'         => '2026-06-12 09:00:00',
        'to'           => '2026-06-12 10:00:00',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    $days = $order->fresh()->clinicGuideDays();

    expect($days)->toHaveCount(2);
    expect($days[1]['date']->toDateString())->toBe('2026-06-11');
});

test('clinicGuideEntryForDate returns null when not planned on that date', function () {
    $order = Order::factory()->create([
        'first_examination_at'   => '2026-06-10',
        'first_examination_time' => '09:00',
    ]);

    expect($order->clinicGuideEntryForDate(Carbon::parse('2026-06-11')))->toBeNull();
});

test('clinicGuideEntryForDate returns correct Carbon for day 1', function () {
    $order = Order::factory()->create([
        'first_examination_at'   => '2026-06-10',
        'first_examination_time' => '09:30',
    ]);

    $entry = $order->clinicGuideEntryForDate(Carbon::parse('2026-06-10'));

    expect($entry)->not->toBeNull();
    expect($entry->format('Y-m-d H:i'))->toBe('2026-06-10 09:30');
});

test('clinicGuideEntryForDate returns earliest slot time for day 2', function () {
    $order = Order::factory()->create([
        'first_examination_at'   => '2026-06-10',
        'first_examination_time' => '09:00',
    ]);
    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'status'   => OrderItemStatus::PLANNED->value,
    ]);

    slotAt($item, '2026-06-11 14:00:00');
    slotAt($item, '2026-06-11 11:00:00');

    $entry = $order->fresh()->clinicGuideEntryForDate(Carbon::parse('2026-06-11'));

    expect($entry)->not->toBeNull();
    expect($entry->format('Y-m-d H:i'))->toBe('2026-06-11 11:00');
});
