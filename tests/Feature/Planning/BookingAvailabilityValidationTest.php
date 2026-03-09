<?php

namespace Tests\Feature\Planning;

use App\Models\OrderItem;
use App\Models\Resource;
use App\Models\Shift;
use Carbon\Carbon;
use Webkul\Installer\Http\Middleware\CanInstall;

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);
    $user = makeUser();
    $this->actingAs($user, 'user');
});

// ---------------------------------------------------------------------------
// Helper: create a resource with a shift that covers the given date,
//         with availability windows on every weekday (1-7) from 09:00-17:00.
// ---------------------------------------------------------------------------
function resourceWithShiftCovering(Carbon $date, bool $allowOutside = false): Resource
{
    $resource = Resource::factory()->create([
        'allow_outside_availability' => $allowOutside,
    ]);

    Shift::factory()->create([
        'resource_id'         => $resource->id,
        'period_start'        => $date->copy()->subDay()->toDateString(),
        'period_end'          => null, // infinite
        'available'           => true,
        'weekday_time_blocks' => [
            1 => [['from' => '09:00', 'to' => '17:00']],
            2 => [['from' => '09:00', 'to' => '17:00']],
            3 => [['from' => '09:00', 'to' => '17:00']],
            4 => [['from' => '09:00', 'to' => '17:00']],
            5 => [['from' => '09:00', 'to' => '17:00']],
            6 => [['from' => '09:00', 'to' => '17:00']],
            7 => [['from' => '09:00', 'to' => '17:00']],
        ],
    ]);

    return $resource->fresh('shifts');
}

// ===========================================================================
// OrderItemPlanningController::book()  — route: admin.planning.order_item.book
// ===========================================================================

test('booking inside shift hours is allowed when allow_outside_availability is false', function () {
    $date = Carbon::tomorrow()->setTime(10, 0);
    $resource = resourceWithShiftCovering($date);
    $orderItem = OrderItem::factory()->create();

    $response = $this->postJson(
        route('admin.planning.order_item.book', ['orderItemId' => $orderItem->id]),
        [
            'resource_id' => $resource->id,
            'from'        => $date->toIso8601String(),
            'to'          => $date->copy()->addHour()->toIso8601String(),
        ]
    );

    $response->assertStatus(201);
    $this->assertDatabaseHas('resource_orderitem', ['resource_id' => $resource->id]);
});

test('booking outside shift hours is blocked when allow_outside_availability is false', function () {
    $date = Carbon::tomorrow()->setTime(18, 0); // 18:00 — buiten 09-17 window
    $resource = resourceWithShiftCovering($date);
    $orderItem = OrderItem::factory()->create();

    $response = $this->postJson(
        route('admin.planning.order_item.book', ['orderItemId' => $orderItem->id]),
        [
            'resource_id' => $resource->id,
            'from'        => $date->toIso8601String(),
            'to'          => $date->copy()->addHour()->toIso8601String(),
        ]
    );

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Het geselecteerde tijdstip valt buiten de beschikbaarheid van deze resource.');

    $this->assertDatabaseMissing('resource_orderitem', ['resource_id' => $resource->id]);
});

test('booking outside shift hours is allowed when allow_outside_availability is true', function () {
    $date = Carbon::tomorrow()->setTime(18, 0);
    $resource = resourceWithShiftCovering($date, allowOutside: true);
    $orderItem = OrderItem::factory()->create();

    $response = $this->postJson(
        route('admin.planning.order_item.book', ['orderItemId' => $orderItem->id]),
        [
            'resource_id' => $resource->id,
            'from'        => $date->toIso8601String(),
            'to'          => $date->copy()->addHour()->toIso8601String(),
        ]
    );

    $response->assertStatus(201);
    $this->assertDatabaseHas('resource_orderitem', ['resource_id' => $resource->id]);
});

test('booking on a day with no shift windows is blocked when allow_outside_availability is false', function () {
    // Create resource with shift only on weekday 1 (Monday)
    $resource = Resource::factory()->create(['allow_outside_availability' => false]);
    Shift::factory()->create([
        'resource_id'         => $resource->id,
        'period_start'        => Carbon::yesterday()->toDateString(),
        'period_end'          => null,
        'available'           => true,
        'weekday_time_blocks' => [
            1 => [['from' => '09:00', 'to' => '17:00']], // alleen maandag
            2 => [],
            3 => [],
            4 => [],
            5 => [],
            6 => [],
            7 => [],
        ],
    ]);
    $resource = $resource->fresh('shifts');

    // Find a non-Monday in the future
    $date = Carbon::tomorrow();
    while ($date->dayOfWeek === Carbon::MONDAY) {
        $date->addDay();
    }
    $date->setTime(10, 0);

    $orderItem = OrderItem::factory()->create();

    $response = $this->postJson(
        route('admin.planning.order_item.book', ['orderItemId' => $orderItem->id]),
        [
            'resource_id' => $resource->id,
            'from'        => $date->toIso8601String(),
            'to'          => $date->copy()->addHour()->toIso8601String(),
        ]
    );

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Het geselecteerde tijdstip valt buiten de beschikbaarheid van deze resource.');
});

test('booking is allowed when resource has no shifts configured', function () {
    $resource = Resource::factory()->create(['allow_outside_availability' => false]);
    $orderItem = OrderItem::factory()->create();

    $date = Carbon::tomorrow()->setTime(18, 0);

    $response = $this->postJson(
        route('admin.planning.order_item.book', ['orderItemId' => $orderItem->id]),
        [
            'resource_id' => $resource->id,
            'from'        => $date->toIso8601String(),
            'to'          => $date->copy()->addHour()->toIso8601String(),
        ]
    );

    // No shifts configured → validation is skipped → booking allowed
    $response->assertStatus(201);
});
