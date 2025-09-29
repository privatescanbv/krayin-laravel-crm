<?php

use App\Http\Controllers\Admin\Settings\ResourceController;
use App\Models\Shift;
use App\Repositories\ClinicRepository;
use App\Repositories\ResourceRepository;
use App\Repositories\ResourceTypeRepository;
use App\Repositories\ShiftRepository;

it('merges blocks and computes net availability minus unavailability', function () {
    // Build two shifts with overlapping Monday blocks, one available and one unavailable
    $shiftAvailable = new Shift([
        'available'           => true,
        'weekday_time_blocks' => [
            1 => [
                ['from' => '08:00', 'to' => '10:00'],
                ['from' => '09:30', 'to' => '12:00'], // overlaps with 08:00-10:00 -> merged 08:00-12:00
                ['from' => '12:00', 'to' => '13:00'], // adjacent to end -> merged into 08:00-13:00
            ],
        ],
    ]);

    $shiftUnavailable = new Shift([
        'available'           => false,
        'weekday_time_blocks' => [
            1 => [
                ['from' => '10:00', 'to' => '11:00'], // should appear under unavailable
                ['from' => '11:00', 'to' => '11:30'], // adjacent -> merge to 10:00-11:30
            ],
            2 => [
                ['from' => '14:00', 'to' => '15:00'],
                ['from' => '14:30', 'to' => '16:00'], // merge to 14:00-16:00 on Tuesday unavailable
            ],
        ],
    ]);

    // Controller instance with mocked dependencies (not used by the method)
    $controller = new ResourceController(
        Mockery::mock(ResourceRepository::class),
        Mockery::mock(ResourceTypeRepository::class),
        Mockery::mock(ShiftRepository::class),
        Mockery::mock(ClinicRepository::class),
    );

    // Access protected method via reflection
    $method = (new ReflectionClass($controller))->getMethod('buildMergedWeeklySummary');
    $method->setAccessible(true);
    $result = $method->invoke($controller, [$shiftAvailable, $shiftUnavailable]);

    // Monday checks (day 1): available 08:00–13:00 minus unavailable 10:00–11:30 => 08:00–10:00 and 11:30–13:00
    expect($result[1]['available'])
        ->toBe([['from' => '08:00', 'to' => '10:00'], ['from' => '11:30', 'to' => '13:00']])
        ->and($result[1]['unavailable'])
        ->toBe([['from' => '10:00', 'to' => '11:30']])
        ->and($result[2]['available'])->toBe([])
        ->and($result[2]['unavailable'])
        ->toBe([['from' => '14:00', 'to' => '16:00']]);

    // Tuesday checks (day 2)
});
