<?php

use App\Models\Resource;
use Illuminate\Support\Carbon;
use Webkul\Installer\Http\Middleware\CanInstall;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);

    $user = makeUser();
    $this->actingAs($user, 'user');
});

it('renders per-period weekly summaries with merged time ranges', function () {

    $resource = Resource::factory()->create();

    // Build dynamic future dates to avoid being filtered out by upcoming query.
    $startA = now()->copy()->addDays(1)->toDateString();
    $startB = now()->copy()->addDays(15)->toDateString();
    $endA = now()->copy()->addDays(31)->toDateString();
    $endB = now()->copy()->addDays(60)->toDateString();
    $startC = Carbon::parse($endB)->addDay()->toDateString();

    // Period A: startA .. endA
    // Available blocks overlap and should merge (08:00-10:00 + 09:30-12:00 => 08:00-12:00)
    $payloadA = [
        'resource_id'         => $resource->id,
        'period_start'        => $startA,
        'period_end'          => $endA,
        'weekday_time_blocks' => [
            1 => [
                ['from' => '08:00', 'to' => '10:00'],
                ['from' => '09:30', 'to' => '12:00'],
            ],
            2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 7 => [],
        ],
        'available' => true,
        'notes'     => 'Period A available',
    ];

    // Period B: startB .. endB (overlaps with A), unavailable for part of day
    $payloadB = [
        'resource_id'         => $resource->id,
        'period_start'        => $startB,
        'period_end'          => $endB,
        'weekday_time_blocks' => [
            1 => [
                ['from' => '10:00', 'to' => '11:00'],
            ],
            2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 7 => [],
        ],
        'available' => false,
        'notes'     => 'Period B unavailable overlap',
    ];

    // Period C: open-ended from the day after endB, available distinct block
    $payloadC = [
        'resource_id'         => $resource->id,
        'period_start'        => $startC,
        'weekday_time_blocks' => [
            1 => [
                ['from' => '13:00', 'to' => '16:00'],
            ],
            2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 7 => [],
        ],
        'available' => true,
        'notes'     => 'Period C open ended',
    ];

    // Create via controller endpoints to ensure validation/casting
    post(route('admin.settings.resources.shifts.store', $resource->id), $payloadA)->assertRedirect();
    post(route('admin.settings.resources.shifts.store', $resource->id), $payloadB)->assertRedirect();
    post(route('admin.settings.resources.shifts.store', $resource->id), $payloadC)->assertRedirect();

    // Visit the bekijken page
    $resp = get(route('admin.settings.resources.show', $resource->id));
    $resp->assertOk();

    // Expect three period segments rendered (A-only, A+B overlap, C-only)
    $segment1Start = Carbon::parse($startA)->toDateString();
    $segment1End = Carbon::parse($startB)->subDay()->toDateString();
    $resp->assertSee('Periode: '.$segment1Start.' — '.$segment1End, false);
    // Monday available merged range from A: 08:00–12:00, no unavailable
    $resp->assertSee('08:00–12:00', false);

    // Segment 2: A+B overlap
    $resp->assertSee('Periode: '.Carbon::parse($startB)->toDateString().' — '.Carbon::parse($endB)->toDateString(), false);
    // For Monday, available still 08:00–12:00 from A; unavailable 10:00–11:00 from B
    $resp->assertSee('10:00–11:00', false);

    // Segment 3: C only (open ended)
    $resp->assertSee('Periode: '.Carbon::parse($startC)->toDateString().' — ∞', false);
    $resp->assertSee('13:00–16:00', false);
});
