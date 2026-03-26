<?php

use App\Http\Controllers\Admin\Settings\ResourceController;
use App\Repositories\ClinicDepartmentRepository;
use App\Repositories\ClinicRepository;
use App\Repositories\ResourceRepository;
use App\Repositories\ResourceTypeRepository;
use App\Repositories\ShiftRepository;
use Illuminate\Support\Carbon;

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
    expect($first['summary'][1]['available'])->toBe([['from' => '08:00', 'to' => '12:00']]);
    expect($first['summary'][1]['unavailable'])->toBe([]);

    // Second period summary corresponds to shiftPeriod2 range
    $second = $result[1];
    expect($second['summary'][1]['available'])->toBe([]);
    expect($second['summary'][1]['unavailable'])->toBe([['from' => '10:00', 'to' => '11:00']]);
});

it('computes net availability with an unavailable sub-period within same month', function () {
    $septStart = Carbon::createFromDate(now()->year, 9, 1)->startOfDay();
    $septEnd = Carbon::createFromDate(now()->year, 9, 30)->startOfDay();
    $firstWeekEnd = $septStart->copy()->addDays(6);

    $availAllMonth = (object) [
        'available'           => true,
        'period_start'        => $septStart->copy(),
        'period_end'          => $septEnd->copy(),
        'weekday_time_blocks' => [
            1 => [['from' => '08:00', 'to' => '17:00']],
            2 => [['from' => '08:00', 'to' => '17:00']],
            3 => [['from' => '08:00', 'to' => '17:00']],
            4 => [['from' => '08:00', 'to' => '17:00']],
            5 => [['from' => '08:00', 'to' => '17:00']],
            6 => [['from' => '08:00', 'to' => '17:00']],
            7 => [['from' => '08:00', 'to' => '17:00']],
        ],
    ];

    $unavailFirstWeek = (object) [
        'available'           => false,
        'period_start'        => $septStart->copy(),
        'period_end'          => $firstWeekEnd->copy(),
        'weekday_time_blocks' => [
            1 => [['from' => '08:00', 'to' => '12:00']],
            2 => [['from' => '08:00', 'to' => '12:00']],
            3 => [['from' => '08:00', 'to' => '12:00']],
            4 => [['from' => '08:00', 'to' => '12:00']],
            5 => [['from' => '08:00', 'to' => '12:00']],
            6 => [['from' => '08:00', 'to' => '12:00']],
            7 => [['from' => '08:00', 'to' => '12:00']],
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
    $result = $method->invoke($controller, [$availAllMonth, $unavailFirstWeek]);

    // Expect at least two segments in September: first week and the remainder
    expect(count($result))->toBeGreaterThanOrEqual(2);

    // First week segment
    $labelFirstWeek = $septStart->format('Y-m-d').' — '.$firstWeekEnd->format('Y-m-d');
    $firstWeek = collect($result)->firstWhere('label', $labelFirstWeek);
    expect($firstWeek)->not()->toBeNull();
    for ($day = 1; $day <= 7; $day++) {
        expect($firstWeek['summary'][$day]['available'])->toBe([['from' => '12:00', 'to' => '17:00']]);
    }

    // Remainder segment
    $remStart = $firstWeekEnd->copy()->addDay();
    $rem = collect($result)->first(function ($seg) use ($remStart, $septEnd) {
        return $seg['start'] === $remStart->format('Y-m-d') && $seg['end'] === $septEnd->format('Y-m-d');
    });
    expect($rem)->not()->toBeNull();
    for ($day = 1; $day <= 7; $day++) {
        expect($rem['summary'][$day]['available'])->toBe([['from' => '08:00', 'to' => '17:00']]);
    }
});

it('shows net availability 12:00–17:00 for first week sub-period within same month', function () {
    // A: whole September available 08:00–17:00
    // B: sub-period 2025-09-05 .. 2025-09-12 unavailable 08:00–12:00
    $year = 2025;
    $septStart = Carbon::createFromDate($year, 9, 1)->startOfDay();
    $septEnd = Carbon::createFromDate($year, 9, 30)->startOfDay();
    $subStart = Carbon::createFromDate($year, 9, 5)->startOfDay();
    $subEnd = Carbon::createFromDate($year, 9, 12)->startOfDay();

    $availAllMonth = (object) [
        'available'           => true,
        'period_start'        => $septStart->copy(),
        'period_end'          => $septEnd->copy(),
        'weekday_time_blocks' => [
            1 => [['from' => '08:00', 'to' => '17:00']],
            2 => [['from' => '08:00', 'to' => '17:00']],
            3 => [['from' => '08:00', 'to' => '17:00']],
            4 => [['from' => '08:00', 'to' => '17:00']],
            5 => [['from' => '08:00', 'to' => '17:00']],
            6 => [['from' => '08:00', 'to' => '17:00']],
            7 => [['from' => '08:00', 'to' => '17:00']],
        ],
    ];

    $unavailSub = (object) [
        'available'           => false,
        'period_start'        => $subStart->copy(),
        'period_end'          => $subEnd->copy(),
        'weekday_time_blocks' => [
            1 => [['from' => '08:00', 'to' => '12:00']],
            2 => [['from' => '08:00', 'to' => '12:00']],
            3 => [['from' => '08:00', 'to' => '12:00']],
            4 => [['from' => '08:00', 'to' => '12:00']],
            5 => [['from' => '08:00', 'to' => '12:00']],
            6 => [['from' => '08:00', 'to' => '12:00']],
            7 => [['from' => '08:00', 'to' => '12:00']],
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
    $result = $method->invoke($controller, [$availAllMonth, $unavailSub]);

    $label = $subStart->format('Y-m-d').' — '.$subEnd->format('Y-m-d');
    $segment = collect($result)->firstWhere('label', $label);
    expect($segment)->not()->toBeNull();

    // Monday availability should be 12:00–17:00
    expect($segment['summary'][1]['available'])->toBe([['from' => '12:00', 'to' => '17:00']]);
});
