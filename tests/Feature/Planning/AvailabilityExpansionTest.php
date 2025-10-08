<?php

namespace Tests\Feature\Planning;

use App\Models\OrderRegel;
use App\Models\Order;
use App\Models\Resource;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityExpansionTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekday_map_blocks_expand_to_availability(): void
    {
        $this->withoutMiddleware();
        // Arrange: resource, order, order item, and shift with weekday map for Mon/Tue/Wed 08:00-17:00
        $order = Order::factory()->create();
        $orderItem = OrderRegel::factory()->create([ 'order_id' => $order->id ]);
        $resource = Resource::factory()->create();

        $weekdayMap = [
            '1' => [ [ 'from' => '08:00', 'to' => '17:00' ] ], // Monday
            '2' => [ [ 'from' => '08:00', 'to' => '17:00' ] ], // Tuesday
            '3' => [ [ 'from' => '08:00', 'to' => '17:00' ] ], // Wednesday
        ];

        Shift::query()->create([
            'resource_id'         => $resource->id,
            'available'           => true,
            'period_start'        => now()->startOfMonth(),
            'period_end'          => now()->endOfMonth(),
            'weekday_time_blocks' => [ $weekdayMap ],
        ]);

        // Act: call availability for the current week
        $start = now()->startOfWeek();
        $end = (clone $start)->addDays(6)->endOfDay();
        $resp = $this->getJson(route('admin.planning.order_item.availability', [
            'orderItemId'      => $orderItem->id,
            'start'            => $start->toIso8601String(),
            'end'              => $end->toIso8601String(),
            'resource_type_id' => $resource->resource_type_id,
            'clinic_id'        => $resource->clinic_id,
        ]));

        // Assert
        $resp->assertOk();
        $data = $resp->json();
        $this->assertArrayHasKey('availability', $data);
        $avail = $data['availability'][(string)$resource->id] ?? $data['availability'][$resource->id] ?? [];
        $this->assertNotEmpty($avail, 'Availability should not be empty');
        // Verify at least one block starts at 08:00 and ends at 17:00 within the requested week
        $hasBlock = collect($avail)->contains(function ($a) use ($start) {
            return str_contains($a['from'], 'T08:00') && str_contains($a['to'], 'T17:00');
        });
        $this->assertTrue($hasBlock, 'Expected 08:00-17:00 block in availability');
    }
}

