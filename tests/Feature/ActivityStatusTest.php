<?php

use App\Enums\ActivityStatus;
use App\Services\ActivityStatusService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it computes status based on dates', function () {
    $now = Carbon::now();

    expect(ActivityStatusService::computeStatus($now->copy()->subHour(), $now->copy()->addHour()))->toBe(ActivityStatus::ACTIVE)
        ->and(ActivityStatusService::computeStatus($now->copy()->subDays(2), $now->copy()->subDay()))->toBe(ActivityStatus::EXPIRED)
        ->and(ActivityStatusService::computeStatus($now->copy()->addDay(), $now->copy()->addDays(2)))->toBe(ActivityStatus::ON_HOLD)
        ->and(ActivityStatusService::computeStatus($now->copy()->subDay(), $now->copy()->addDay(), ActivityStatus::IN_PROGRESS))->toBe(ActivityStatus::IN_PROGRESS);
});

// Legacy mapping test removed; data will be reset and code not retained.
