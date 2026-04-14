<?php

use App\Enums\ActivityType;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Models\Activity;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

test('file activity store persists order_id and sales_lead_id from multipart request', function () {
    $roleId = DB::table('roles')->insertGetId([
        'name'            => 'Tester',
        'description'     => 'Test role',
        'permission_type' => 'all',
        'permissions'     => json_encode([]),
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $userId = DB::table('users')->insertGetId([
        'first_name' => 'Test',
        'last_name'  => 'User',
        'email'      => 'order-file-act@example.com',
        'password'   => bcrypt('secret'),
        'status'     => 1,
        'role_id'    => $roleId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::find($userId);
    $this->actingAs($user, 'user');

    $order = Order::factory()->create();

    $file = UploadedFile::fake()->create('scan.pdf', 12, 'application/pdf');

    $response = $this->post(route('admin.activities.store'), [
        'type'                => 'file',
        'order_id'            => (string) $order->id,
        'sales_lead_id'       => (string) $order->sales_lead_id,
        'title'               => 'Testbestand',
        'comment'             => 'Omschrijving',
        'file'                => $file,
        'publish_to_portal'   => '0',
    ], [
        'Accept'           => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response->assertSuccessful();

    $activity = Activity::query()
        ->where('type', ActivityType::FILE)
        ->where('order_id', $order->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->sales_lead_id)->toBe($order->sales_lead_id);
});
