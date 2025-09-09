<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Services\ActivityStatusService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityStatusTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_computes_status_based_on_dates()
    {
        $now = Carbon::now();

        $this->assertEquals(ActivityStatus::ACTIVE, ActivityStatusService::computeStatus($now->copy()->subHour(), $now->copy()->addHour()));
        $this->assertEquals(ActivityStatus::EXPIRED, ActivityStatusService::computeStatus($now->copy()->subDays(2), $now->copy()->subDay()));
        $this->assertEquals(ActivityStatus::ON_HOLD, ActivityStatusService::computeStatus($now->copy()->addDay(), $now->copy()->addDays(2)));
        $this->assertEquals(ActivityStatus::IN_PROGRESS, ActivityStatusService::computeStatus($now->copy()->subDay(), $now->copy()->addDay(), ActivityStatus::IN_PROGRESS));
    }

    /** @test */
    // Legacy mapping test removed; data will be reset and code not retained.
}
