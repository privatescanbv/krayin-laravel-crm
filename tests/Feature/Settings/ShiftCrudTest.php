<?php

use App\Models\Resource;
use App\Models\User;
use App\Repositories\ShiftRepository;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

it('can list, create, update and delete shifts', function () {
    $user = User::factory()->create();
    actingAs($user, 'user');

    $resource = Resource::factory()->create();

    // List page
    $resp = get(route('admin.settings.resources.shifts.index', $resource->id));
    $resp->assertOk();

    // Create
    $payload = [
        'resource_id'         => $resource->id,
        'period_start'        => now()->toDateString(),
        'period_end'          => now()->copy()->addMonth()->toDateString(),
        'weekday_time_blocks' => [
            1 => [['from' => '08:00', 'to' => '12:00']],
            2 => [],
            3 => [],
            4 => [],
            5 => [],
            6 => [],
            7 => [],
        ],
        'available' => true,
    ];

    $resp = post(route('admin.settings.resources.shifts.store', $resource->id), $payload);
    $resp->assertRedirect(route('admin.settings.resources.shifts.index', $resource->id));

    $shift = app(ShiftRepository::class)->forResource($resource->id)->first();
    expect($shift)->not()->toBeNull();

    // Update
    $payload['weekday_time_blocks'][1][] = ['from' => '13:00', 'to' => '16:00'];
    $resp = put(route('admin.settings.resources.shifts.update', [$resource->id, $shift->id]), $payload);
    $resp->assertRedirect(route('admin.settings.resources.shifts.index', $resource->id));

    $shift->refresh();
    expect(count($shift->weekday_time_blocks[1]))->toBe(2);

    // Delete
    $resp = delete(route('admin.settings.resources.shifts.delete', [$resource->id, $shift->id]));
    $resp->assertRedirect(route('admin.settings.resources.shifts.index', $resource->id));
});

it('rejects time blocks where end is not after start', function () {
    $user = User::factory()->create();
    actingAs($user, 'user');

    $resource = Resource::factory()->create();

    $payload = [
        'resource_id'         => $resource->id,
        'period_start'        => now()->toDateString(),
        'weekday_time_blocks' => [
            1 => [['from' => '10:00', 'to' => '09:59']],
        ],
    ];

    $resp = post(route('admin.settings.resources.shifts.store', $resource->id), $payload);
    $resp->assertSessionHasErrors(['weekday_time_blocks.1.0.to']);
});
