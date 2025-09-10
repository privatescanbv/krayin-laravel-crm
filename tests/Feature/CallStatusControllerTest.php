<?php

namespace Tests\Feature;

use App\Enums\CallStatus as CallStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Webkul\Activity\Models\Activity;
use Webkul\User\Models\User;

class CallStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function spoken_does_not_reschedule_activity()
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'Tester',
            'description' => 'Test role',
            'permission_type' => 'all',
            'permissions' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('secret'),
            'status' => 1,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::find($userId);
        $this->actingAs($user, 'user');

        $activity = Activity::create([
            'title' => 'Call',
            'type' => 'call',
            'schedule_from' => now(),
            'schedule_to' => now()->addDay(),
            'user_id' => $user->id,
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
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'Tester 2',
            'description' => 'Test role 2',
            'permission_type' => 'all',
            'permissions' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'name' => 'Test User 2',
            'email' => 'test2@example.com',
            'password' => bcrypt('secret'),
            'status' => 1,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::find($userId);
        $this->actingAs($user, 'user');

        $activity = Activity::create([
            'title' => 'Call 2',
            'type' => 'call',
            'schedule_from' => now(),
            'schedule_to' => now()->addDay(),
            'user_id' => $user->id,
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

