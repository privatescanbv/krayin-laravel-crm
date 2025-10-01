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

it('can create and edit shift with null period_end for infinite duration', function () {
    $user = User::factory()->create();
    actingAs($user, 'user');

    $resource = Resource::factory()->create();

    // Create shift without period_end (infinite)
    $payload = [
        'resource_id'         => $resource->id,
        'period_start'        => now()->toDateString(),
        'period_end'          => null, // Oneindig
        'weekday_time_blocks' => [
            1 => [['from' => '08:00', 'to' => '17:00']],
            2 => [['from' => '08:00', 'to' => '17:00']],
            3 => [['from' => '08:00', 'to' => '17:00']],
            4 => [['from' => '08:00', 'to' => '17:00']],
            5 => [['from' => '08:00', 'to' => '17:00']],
            6 => [],
            7 => [],
        ],
        'available' => true,
        'notes'     => 'Standaard werkweek, oneindig geldig',
    ];

    $resp = post(route('admin.settings.resources.shifts.store', $resource->id), $payload);
    $resp->assertRedirect(route('admin.settings.resources.shifts.index', $resource->id));

    // Verify shift was created with null period_end
    $shift = app(ShiftRepository::class)->forResource($resource->id)->first();
    expect($shift)->not()->toBeNull();
    expect($shift->period_start)->toBe($payload['period_start']);
    expect($shift->period_end)->toBeNull(); // Moet null zijn voor oneindig
    expect($shift->notes)->toBe('Standaard werkweek, oneindig geldig');

    // Edit form should load correctly with null period_end
    $editResp = get(route('admin.settings.resources.shifts.edit', [$resource->id, $shift->id]));
    $editResp->assertOk();
    // Verify page loads without errors (no foreach issue on null)

    // Update shift, keep period_end as null
    $payload['notes'] = 'Updated: Nog steeds oneindig geldig';
    $payload['period_end'] = null;
    
    $updateResp = put(route('admin.settings.resources.shifts.update', [$resource->id, $shift->id]), $payload);
    $updateResp->assertRedirect(route('admin.settings.resources.shifts.index', $resource->id));

    // Verify period_end is still null after update
    $shift->refresh();
    expect($shift->period_end)->toBeNull();
    expect($shift->notes)->toBe('Updated: Nog steeds oneindig geldig');
});

it('can create shift with period_end for finite duration', function () {
    $user = User::factory()->create();
    actingAs($user, 'user');

    $resource = Resource::factory()->create();

    // Create shift WITH period_end (finite)
    $startDate = now()->toDateString();
    $endDate = now()->addMonths(3)->toDateString();

    $payload = [
        'resource_id'         => $resource->id,
        'period_start'        => $startDate,
        'period_end'          => $endDate,
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
        'notes'     => 'Tijdelijke shift van 3 maanden',
    ];

    $resp = post(route('admin.settings.resources.shifts.store', $resource->id), $payload);
    $resp->assertRedirect(route('admin.settings.resources.shifts.index', $resource->id));

    // Verify shift was created with period_end
    $shift = app(ShiftRepository::class)->forResource($resource->id)->first();
    expect($shift)->not()->toBeNull();
    expect($shift->period_start)->toBe($startDate);
    expect($shift->period_end)->toBe($endDate);

    // Edit form should load correctly
    $editResp = get(route('admin.settings.resources.shifts.edit', [$resource->id, $shift->id]));
    $editResp->assertOk();
});
