<?php

use App\Http\Controllers\Admin\Settings\ResourceController;
use App\Models\Shift;
use App\Repositories\ClinicRepository;
use App\Repositories\ResourceRepository;
use App\Repositories\ResourceTypeRepository;
use App\Repositories\ShiftRepository;
use Illuminate\Support\Carbon;
use Mockery;

it('produces two distinct periods in period-aware summaries', function () {
    // Two shifts in non-overlapping date ranges
    $shiftPeriod1 = new Shift([
        'available' => true,
        'period_start' => Carbon::now()->addDays(1),
        'period_end' => Carbon::now()->addDays(10),
        'weekday_time_blocks' => [
            1 => [ ['from' => '08:00', 'to' => '12:00'] ],
        ],
    ]);

    $shiftPeriod2 = new Shift([
        'available' => false,
        'period_start' => Carbon::now()->addDays(20),
        'period_end' => Carbon::now()->addDays(30),
        'weekday_time_blocks' => [
            1 => [ ['from' => '10:00', 'to' => '11:00'] ],
        ],
    ]);

    $controller = new ResourceController(
        Mockery::mock(ResourceRepository::class),
        Mockery::mock(ResourceTypeRepository::class),
        Mockery::mock(ShiftRepository::class),
        Mockery::mock(ClinicRepository::class),
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

