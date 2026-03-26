<?php

use App\Http\Controllers\Admin\Settings\ResourceController;
use App\Repositories\ClinicDepartmentRepository;
use App\Repositories\ClinicRepository;
use App\Repositories\ResourceRepository;
use App\Repositories\ResourceTypeRepository;
use App\Repositories\ShiftRepository;
use Illuminate\Support\Carbon;

it('merges blocks and computes net availability minus unavailability', function () {
    // Build two shifts with overlapping Monday blocks, one available and one unavailable
    $shiftAvailable = (object) [
        'available'           => true,
        'weekday_time_blocks' => [
            1 => [
                ['from' => '08:00', 'to' => '10:00'],
                ['from' => '09:30', 'to' => '12:00'], // overlaps with 08:00-10:00 -> merged 08:00-12:00
                ['from' => '12:00', 'to' => '13:00'], // adjacent to end -> merged into 08:00-13:00
            ],
        ],
    ];

    $shiftUnavailable = (object) [
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
    ];

    // Controller instance with mocked dependencies (not used by the method)
    $controller = new ResourceController(
        Mockery::mock(ResourceRepository::class),
        Mockery::mock(ResourceTypeRepository::class),
        Mockery::mock(ShiftRepository::class),
        Mockery::mock(ClinicRepository::class),
        Mockery::mock(ClinicDepartmentRepository::class),
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

it('produces two distinct periods in period-aware summaries', function () {
    // Two shifts in non-overlapping date ranges
    $shiftPeriod1 = (object) [
        'available'           => true,
        'period_start'        => Carbon::now()->addDays(1),
        'period_end'          => Carbon::now()->addDays(10),
        'weekday_time_blocks' => [
            1 => [['from' => '08:00', 'to' => '12:00']],
        ],
    ];

    $shiftPeriod2 = (object) [
        'available'           => false,
        'period_start'        => Carbon::now()->addDays(20),
        'period_end'          => Carbon::now()->addDays(30),
        'weekday_time_blocks' => [
            1 => [['from' => '10:00', 'to' => '11:00']],
        ],
    ];

    $controller = new ResourceController(
        Mockery::mock(ResourceRepository::class),
        Mockery::mock(ResourceTypeRepository::class),
        Mockery::mock(ShiftRepository::class),
        Mockery::mock(ClinicRepository::class),
        Mockery::mock(ClinicDepartmentRepository::class),
    );

    $method = (new ReflectionClass($controller))->getMethod('buildPeriodAwareWeeklySummaries');
    $method->setAccessible(true);
    $result = $method->invoke($controller, [$shiftPeriod1, $shiftPeriod2]);

    expect($result)->toBeArray();
    expect(count($result))->toBe(2);

    // First period summary corresponds to shiftPeriod1 range
    $first = $result[0];
    expect($first['summary'][1]['available'])->toBe([['from' => '08:00', 'to' => '12:00']])
        ->and($first['summary'][1]['unavailable'])->toBe([]);

    // Second period summary corresponds to shiftPeriod2 range
    $second = $result[1];
    expect($second['summary'][1]['available'])->toBe([])
        ->and($second['summary'][1]['unavailable'])->toBe([['from' => '10:00', 'to' => '11:00']]);
});
