<?php

namespace Tests\Feature;

use App\Enums\CallStatus as CallStatusEnum;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Webkul\Activity\Models\Activity;
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

class CallStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function spoken_does_not_reschedule_activity()
    {
        $roleId = DB::table('roles')->insertGetId([
            'name'            => 'Tester',
            'description'     => 'Test role',
            'permission_type' => 'all',
            'permissions'     => json_encode([]),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'name'       => 'Test User',
            'email'      => 'test@example.com',
            'password'   => bcrypt('secret'),
            'status'     => 1,
            'role_id'    => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::find($userId);
        $this->actingAs($user, 'user');

        $activity = Activity::create([
            'title'         => 'Call',
            'type'          => 'call',
            'schedule_from' => now(),
            'schedule_to'   => now()->addDay(),
            'user_id'       => $user->id,
        ]);

        $response = $this->postJson(route('admin.activities.call-statuses.store', $activity->id), [
            'status'          => CallStatusEnum::SPOKEN->value,
            'omschrijving'    => null,
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
            'name'            => 'Tester 2',
            'description'     => 'Test role 2',
            'permission_type' => 'all',
            'permissions'     => json_encode([]),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'name'       => 'Test User 2',
            'email'      => 'test2@example.com',
            'password'   => bcrypt('secret'),
            'status'     => 1,
            'role_id'    => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::find($userId);
        $this->actingAs($user, 'user');

        $activity = Activity::create([
            'title'         => 'Call 2',
            'type'          => 'call',
            'schedule_from' => now(),
            'schedule_to'   => now()->addDay(),
            'user_id'       => $user->id,
        ]);

        $response = $this->postJson(route('admin.activities.call-statuses.store', $activity->id), [
            'status'          => CallStatusEnum::NOT_REACHABLE->value,
            'omschrijving'    => null,
            'reschedule_days' => '',
        ]);

        $response->assertOk();
        $activity->refresh();
        $this->assertTrue($activity->schedule_from->isSameDay(now()->addDays(7)));
    }

    /** @test */
    public function call_status_with_send_email_returns_email_data()
    {
        $roleId = DB::table('roles')->insertGetId([
            'name'            => 'Tester 3',
            'description'     => 'Test role 3',
            'permission_type' => 'all',
            'permissions'     => json_encode([]),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'name'       => 'Test User 3',
            'email'      => 'test3@example.com',
            'password'   => bcrypt('secret'),
            'status'     => 1,
            'role_id'    => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::find($userId);
        $this->actingAs($user, 'user');

        // Create a lead with email
        $lead = Lead::create([
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'emails'     => [
                ['value' => 'john.doe@example.com', 'is_default' => true],
            ],
            'user_id'    => $user->id,
        ]);

        $activity = Activity::create([
            'title'         => 'Call 3',
            'type'          => 'call',
            'schedule_from' => now(),
            'schedule_to'   => now()->addDay(),
            'user_id'       => $user->id,
            'lead_id'       => $lead->id,
        ]);

        $response = $this->postJson(route('admin.activities.call-statuses.store', $activity->id), [
            'status'          => CallStatusEnum::SPOKEN->value,
            'omschrijving'    => 'Test call status',
            'reschedule_days' => '',
            'send_email'      => true,
        ]);

        $response->assertOk();
        $responseData = $response->json();

        $this->assertTrue($responseData['send_email']);
        $this->assertEquals('john.doe@example.com', $responseData['default_email']);
        $this->assertEquals($activity->id, $responseData['activity_id']);
    }

    /** @test */
    public function activity_can_be_linked_to_email()
    {
        $roleId = DB::table('roles')->insertGetId([
            'name'            => 'Tester 4',
            'description'     => 'Test role 4',
            'permission_type' => 'all',
            'permissions'     => json_encode([]),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'name'       => 'Test User 4',
            'email'      => 'test4@example.com',
            'password'   => bcrypt('secret'),
            'status'     => 1,
            'role_id'    => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::find($userId);
        $this->actingAs($user, 'user');

        // Create an email
        $email = Email::create([
            'subject'   => 'Test Email',
            'reply'     => 'Test email content',
            'from'      => ['test@example.com'],
            'reply_to'  => ['recipient@example.com'],
            'user_type' => 'user',
            'is_read'   => 0,
            'source'    => 'test',
            'message_id'=> (string) Str::uuid(),
        ]);

        // Create an activity
        $activity = Activity::create([
            'title'         => 'Test Activity',
            'type'          => 'call',
            'schedule_from' => now(),
            'schedule_to'   => now()->addDay(),
            'user_id'       => $user->id,
            'email_id'      => $email->id,
        ]);

        // Test the relationship
        $this->assertEquals($email->id, $activity->email_id);
        $this->assertTrue($activity->email->is($email));
        $this->assertTrue($email->activities->contains($activity));
    }

    /** @test */
    public function activity_email_relationship_works_correctly()
    {
        $roleId = DB::table('roles')->insertGetId([
            'name'            => 'Tester 5',
            'description'     => 'Test role 5',
            'permission_type' => 'all',
            'permissions'     => json_encode([]),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'name'       => 'Test User 5',
            'email'      => 'test5@example.com',
            'password'   => bcrypt('secret'),
            'status'     => 1,
            'role_id'    => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::find($userId);
        $this->actingAs($user, 'user');

        // Create an email
        $email = Email::create([
            'subject'   => 'Test Email for Activity',
            'reply'     => 'Test email content for activity',
            'from'      => ['sender@example.com'],
            'reply_to'  => ['recipient@example.com'],
            'user_type' => 'user',
            'is_read'   => 0,
            'source'    => 'test',
            'message_id'=> (string) Str::uuid(),
        ]);

        // Create multiple activities linked to the same email
        $activity1 = Activity::create([
            'title'         => 'Activity 1',
            'type'          => 'call',
            'schedule_from' => now(),
            'schedule_to'   => now()->addDay(),
            'user_id'       => $user->id,
            'email_id'      => $email->id,
        ]);

        $activity2 = Activity::create([
            'title'         => 'Activity 2',
            'type'          => 'meeting',
            'schedule_from' => now()->addDay(),
            'schedule_to'   => now()->addDays(2),
            'user_id'       => $user->id,
            'email_id'      => $email->id,
        ]);

        // Test relationships
        $this->assertCount(2, $email->activities);
        $this->assertTrue($email->activities->contains($activity1));
        $this->assertTrue($email->activities->contains($activity2));

        $this->assertEquals($email->id, $activity1->email_id);
        $this->assertEquals($email->id, $activity2->email_id);
    }
}
