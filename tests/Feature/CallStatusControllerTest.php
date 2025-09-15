<?php

namespace Tests\Feature;

use App\Enums\CallStatus as CallStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        ]);

        // Link email to activity (email belongs to one activity)
        $email->activity_id = $activity->id;
        $email->save();

        // Test the relationship
        $this->assertTrue($email->activity->is($activity));
        $this->assertTrue($activity->emails->contains($email));
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

        // Create an activity
        $activity = Activity::create([
            'title'         => 'Activity Container',
            'type'          => 'call',
            'schedule_from' => now(),
            'schedule_to'   => now()->addDay(),
            'user_id'       => $user->id,
        ]);

        // Create multiple emails linked to the same activity
        $email1 = Email::create([
            'subject'    => 'E1',
            'reply'      => 'Body 1',
            'from'       => ['sender1@example.com'],
            'reply_to'   => ['recipient1@example.com'],
            'user_type'  => 'user',
            'is_read'    => 0,
            'source'     => 'test',
            'message_id' => (string) Str::uuid(),
            'activity_id'=> $activity->id,
        ]);

        $email2 = Email::create([
            'subject'    => 'E2',
            'reply'      => 'Body 2',
            'from'       => ['sender2@example.com'],
            'reply_to'   => ['recipient2@example.com'],
            'user_type'  => 'user',
            'is_read'    => 0,
            'source'     => 'test',
            'message_id' => (string) Str::uuid(),
            'activity_id'=> $activity->id,
        ]);

        // Reload relations
        $activity->load('emails');

        // Test relationships
        $this->assertCount(2, $activity->emails);
        $this->assertTrue($activity->emails->contains($email1));
        $this->assertTrue($activity->emails->contains($email2));
        $this->assertTrue($email1->activity->is($activity));
        $this->assertTrue($email2->activity->is($activity));
    }
}
