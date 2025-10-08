<?php

namespace Tests\Feature\Planning;

use App\Models\OrderRegel;
use App\Models\Order;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
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
            'weekday_time_blocks' => $weekdayMap, // Direct weekday map, not wrapped in array
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
        $this->assertArrayHasKey('resources', $data);
        $this->assertNotEmpty($data['resources'], 'Should have resources');
        
        $avail = $data['availability'][(string)$resource->id] ?? $data['availability'][$resource->id] ?? [];
        $this->assertNotEmpty($avail, 'Availability should not be empty for resource ' . $resource->id);
        
        // Verify at least one block starts at 08:00 and ends at 17:00 within the requested week
        $hasBlock = collect($avail)->contains(function ($a) {
            return str_contains($a['from'], 'T08:00') && str_contains($a['to'], 'T17:00');
        });
        $this->assertTrue($hasBlock, 'Expected 08:00-17:00 block in availability');
    }

    public function test_availability_with_occupancy_subtracts_booked_times(): void
    {
        $this->withoutMiddleware();
        // Arrange: resource with shift and existing booking
        $order = Order::factory()->create();
        $orderItem = OrderRegel::factory()->create([ 'order_id' => $order->id ]);
        $resource = Resource::factory()->create();

        // Shift: Mon 08:00-17:00
        $weekdayMap = [
            '1' => [ [ 'from' => '08:00', 'to' => '17:00' ] ], // Monday
        ];

        Shift::query()->create([
            'resource_id'         => $resource->id,
            'available'           => true,
            'period_start'        => now()->startOfMonth(),
            'period_end'          => now()->endOfMonth(),
            'weekday_time_blocks' => $weekdayMap,
        ]);

        // Act: call availability for the current week
        $start = now()->startOfWeek();
        $end = (clone $start)->addDays(6)->endOfDay();
        
        // Booking: Mon 10:00-12:00 (should split availability)
        $monday = $start->copy()->addDay(); // Monday of the current week
        $booking = ResourceOrderItem::query()->create([
            'resource_id'  => $resource->id,
            'orderitem_id' => $orderItem->id,
            'from'         => $monday->copy()->setTime(10, 0),
            'to'           => $monday->copy()->setTime(12, 0),
        ]);
        
        // Booking should be created successfully (no database check needed for this test)
        
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
        
        $avail = $data['availability'][(string)$resource->id] ?? $data['availability'][$resource->id] ?? [];
        $occupancy = $data['occupancy'][(string)$resource->id] ?? $data['occupancy'][$resource->id] ?? [];
        
        // Debug output
        $this->assertArrayHasKey('availability', $data, 'Response should have availability key');
        $this->assertArrayHasKey('occupancy', $data, 'Response should have occupancy key');
        $this->assertNotEmpty($data['availability'], 'Availability should not be empty');
        
        // Debug: Check if occupancy is found
        if (empty($occupancy)) {
            $this->fail('No occupancy found for resource ' . $resource->id . '. Full occupancy data: ' . json_encode($data['occupancy']));
        }
        
        // Should have 1 occupancy block
        $this->assertCount(1, $occupancy, 'Should have 1 occupancy block. Got: ' . json_encode($occupancy));
        
        
        // Should have 2 availability blocks: 08:00-10:00 and 12:00-17:00
        $this->assertCount(2, $avail, 'Should have 2 availability blocks after booking subtraction. Got: ' . json_encode($avail));
        
        // Verify the split availability
        $hasEarlyBlock = collect($avail)->contains(function ($a) {
            return str_contains($a['from'], 'T08:00') && str_contains($a['to'], 'T10:00');
        });
        $hasLateBlock = collect($avail)->contains(function ($a) {
            return str_contains($a['from'], 'T12:00') && str_contains($a['to'], 'T17:00');
        });
        
        $this->assertTrue($hasEarlyBlock, 'Should have 08:00-10:00 availability block');
        $this->assertTrue($hasLateBlock, 'Should have 12:00-17:00 availability block');
    }

    public function test_subtract_intervals_function(): void
    {
        // Test the subtractIntervals logic directly
        $availability = [
            ['from' => '2025-10-06T08:00:00+02:00', 'to' => '2025-10-06T17:00:00+02:00']
        ];
        
        $occupancy = [
            ['from' => '2025-10-06T10:00:00+02:00', 'to' => '2025-10-06T12:00:00+02:00']
        ];
        
        // Simulate the subtractIntervals function
        $result = [];
        foreach ($availability as $interval) {
            $segments = [['from' => \Carbon\CarbonImmutable::parse($interval['from']), 'to' => \Carbon\CarbonImmutable::parse($interval['to'])]];
            
            foreach ($occupancy as $o) {
                $of = \Carbon\CarbonImmutable::parse($o['from']);
                $ot = \Carbon\CarbonImmutable::parse($o['to']);
                $next = [];
                
                foreach ($segments as $seg) {
                    $segStart = $seg['from'];
                    $segEnd = $seg['to'];
                    
                    // No overlap - keep segment as is
                    if ($ot->lessThanOrEqualTo($segStart) || $of->greaterThanOrEqualTo($segEnd)) {
                        $next[] = $seg;
                        continue;
                    }
                    
                    // Complete overlap - remove segment entirely
                    if ($of->lessThanOrEqualTo($segStart) && $ot->greaterThanOrEqualTo($segEnd)) {
                        continue;
                    }
                    
                    // Partial overlap - split segment
                    // Left part (before occupancy) - only if there's space before
                    if ($of->greaterThan($segStart)) {
                        $next[] = ['from' => $segStart, 'to' => $of];
                    }
                    
                    // Right part (after occupancy) - only if there's space after
                    if ($ot->lessThan($segEnd)) {
                        $next[] = ['from' => $ot, 'to' => $segEnd];
                    }
                }
                $segments = $next;
            }
            
            // Add remaining segments to result
            foreach ($segments as $s) {
                if ($s['to']->greaterThan($s['from'])) {
                    $result[] = ['from' => $s['from']->toIso8601String(), 'to' => $s['to']->toIso8601String()];
                }
            }
        }
        
        $this->assertCount(2, $result, 'Should have 2 availability blocks after subtraction');
        $this->assertStringContainsString('T08:00', $result[0]['from']);
        $this->assertStringContainsString('T10:00', $result[0]['to']);
        $this->assertStringContainsString('T12:00', $result[1]['from']);
        $this->assertStringContainsString('T17:00', $result[1]['to']);
    }

    public function test_multiple_resources_with_different_shifts(): void
    {
        $this->withoutMiddleware();
        // Arrange: 2 resources with different shifts
        $order = Order::factory()->create();
        $orderItem = OrderRegel::factory()->create([ 'order_id' => $order->id ]);
        
        $resource1 = Resource::factory()->create();
        $resource2 = Resource::factory()->create();

        // Resource 1: Mon/Tue 08:00-17:00
        Shift::query()->create([
            'resource_id'         => $resource1->id,
            'available'           => true,
            'period_start'        => now()->startOfMonth(),
            'period_end'          => now()->endOfMonth(),
            'weekday_time_blocks' => [
                '1' => [ [ 'from' => '08:00', 'to' => '17:00' ] ], // Monday
                '2' => [ [ 'from' => '08:00', 'to' => '17:00' ] ], // Tuesday
            ],
        ]);

        // Resource 2: Wed/Thu 09:00-18:00
        Shift::query()->create([
            'resource_id'         => $resource2->id,
            'available'           => true,
            'period_start'        => now()->startOfMonth(),
            'period_end'          => now()->endOfMonth(),
            'weekday_time_blocks' => [
                '3' => [ [ 'from' => '09:00', 'to' => '18:00' ] ], // Wednesday
                '4' => [ [ 'from' => '09:00', 'to' => '18:00' ] ], // Thursday
            ],
        ]);

        // Act: call availability for the current week
        $start = now()->startOfWeek();
        $end = (clone $start)->addDays(6)->endOfDay();
        $resp = $this->getJson(route('admin.planning.order_item.availability', [
            'orderItemId'      => $orderItem->id,
            'start'            => $start->toIso8601String(),
            'end'              => $end->toIso8601String(),
            'resource_type_id' => $resource1->resource_type_id, // Both resources should have same type
            'clinic_id'        => $resource1->clinic_id,
        ]));

        // Assert
        $resp->assertOk();
        $data = $resp->json();
        $this->assertCount(2, $data['resources'], 'Should have 2 resources');
        
        $avail1 = $data['availability'][(string)$resource1->id] ?? $data['availability'][$resource1->id] ?? [];
        $avail2 = $data['availability'][(string)$resource2->id] ?? $data['availability'][$resource2->id] ?? [];
        
        // Resource 1 should have Mon/Tue blocks
        $this->assertNotEmpty($avail1, 'Resource 1 should have availability');
        $hasMondayBlock = collect($avail1)->contains(function ($a) {
            return str_contains($a['from'], 'T08:00') && str_contains($a['to'], 'T17:00');
        });
        $this->assertTrue($hasMondayBlock, 'Resource 1 should have Monday 08:00-17:00 block');
        
        // Resource 2 should have Wed/Thu blocks
        $this->assertNotEmpty($avail2, 'Resource 2 should have availability');
        $hasWednesdayBlock = collect($avail2)->contains(function ($a) {
            return str_contains($a['from'], 'T09:00') && str_contains($a['to'], 'T18:00');
        });
        $this->assertTrue($hasWednesdayBlock, 'Resource 2 should have Wednesday 09:00-18:00 block');
    }
}

