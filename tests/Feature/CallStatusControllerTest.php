<?php

namespace Tests\Feature;

use App\Enums\CallStatus as CallStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Webkul\Activity\Models\Activity;
use Webkul\User\Models\User;

class CallStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function spoken_does_not_reschedule_activity()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'user');

        $activity = Activity::factory()->create([
            'type' => 'call',
            'schedule_from' => now(),
            'schedule_to' => now()->addDay(),
        ]);

        $response = $this->postJson(route('admin.activities.call-statuses.store', $activity->id), [
            'status' => CallStatusEnum::SPOKEN->value,
            'omschrijving' => null,
            'reschedule_days' => '',
        ]);

        $response->assertOk();
        $activity->refresh();
        $this->assertTrue($activity->schedule_from->isToday());
    }

    /** @test */
    public function not_spoken_defaults_to_7_days_when_empty()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'user');

        $activity = Activity::factory()->create([
            'type' => 'call',
            'schedule_from' => now(),
            'schedule_to' => now()->addDay(),
        ]);

        $response = $this->postJson(route('admin.activities.call-statuses.store', $activity->id), [
            'status' => CallStatusEnum::NOT_REACHABLE->value,
            'omschrijving' => null,
            'reschedule_days' => '',
        ]);

        $response->assertOk();
        $activity->refresh();
        $this->assertTrue($activity->schedule_from->isSameDay(now()->addDays(7)));
    }
}

