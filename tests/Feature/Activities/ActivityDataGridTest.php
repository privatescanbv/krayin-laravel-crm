<?php

namespace Tests\Feature\Activities;

use App\Enums\ActivityType;
use App\Enums\PipelineStage;
use App\Services\ActivityQueueRegistry;
use App\Services\ActivityQueueRepository;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Carbon;
use Webkul\Activity\Models\Activity;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Group;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

use function Pest\Laravel\get;

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

it('renders deadline column directly from activities_schedule_to', function () {
    // Arrange
    $adminRole = Role::factory()->create([
        'permission_type' => 'all',
        'permissions'     => null,
    ]);

    $admin = User::factory()->create([
        'role_id'         => $adminRole->id,
        'view_permission' => 'global',
        'status'          => 1,
    ]);

    /** @var Group $group */
    $group = Group::query()->firstOrFail();

    // Fixed, easily recognizable deadline
    $scheduleTo = '2026-03-16 12:00:00';

    $activity = Activity::create([
        'type'          => ActivityType::CALL->value,
        'user_id'       => $admin->id,
        'title'         => 'Grid deadline test',
        'schedule_from' => '2026-03-16 10:00:00',
        'schedule_to'   => $scheduleTo,
        'is_done'       => 0,
        'group_id'      => $group->id,
    ]);

    // Act
    $this->actingAs($admin, 'user');
    $response = get('/admin/activities/get');

    $response->assertOk();

    $payload = $response->json();
    $record = collect($payload['records'] ?? [])
        ->firstWhere('id', $activity->id);

    expect($record)->not()->toBeNull();

    // The schedule_to column is rendered as a HTML span containing the formatted date.
    $expected = '16-03-2026 12:00';
    expect($record['schedule_to'] ?? '')
        ->toContain($expected);
});

it('filters frontoffice queue by lead pipeline stage', function () {
    // Arrange
    $adminRole = Role::factory()->create([
        'permission_type' => 'all',
        'permissions'     => null,
    ]);

    $admin = User::factory()->create([
        'role_id'         => $adminRole->id,
        'view_permission' => 'global',
        'status'          => 1,
    ]);

    /** @var Group $group */
    $group = Group::query()->firstOrFail();

    // Frontoffice lead (Nieuwe aanvraag, kwalificeren)
    $frontofficeLead = Lead::factory()->create([
        'lead_pipeline_id'       => PipelineStage::NIEUWE_AANVRAAG_KWALIFICEREN->pipeline(),
        'lead_pipeline_stage_id' => PipelineStage::NIEUWE_AANVRAAG_KWALIFICEREN->id(),
    ]);

    $frontofficeActivity = Activity::create([
        'type'          => ActivityType::TASK->value,
        'user_id'       => $admin->id,
        'title'         => 'Frontoffice activity',
        'schedule_from' => now()->subDay(),
        'schedule_to'   => now()->addDay(),
        'is_done'       => 0,
        'group_id'      => $group->id,
        'lead_id'       => $frontofficeLead->id,
    ]);

    // Lead in a different stage, should NOT be in frontoffice queue.
    $otherLead = Lead::factory()->create([
        'lead_pipeline_id'       => PipelineStage::KLANT_ADVISEREN_START->pipeline(),
        'lead_pipeline_stage_id' => PipelineStage::KLANT_ADVISEREN_START->id(),
    ]);

    $otherActivity = Activity::create([
        'type'          => ActivityType::TASK->value,
        'user_id'       => $admin->id,
        'title'         => 'Other queue activity',
        'schedule_from' => now()->subDay(),
        'schedule_to'   => now()->addDay(),
        'is_done'       => 0,
        'group_id'      => $group->id,
        'lead_id'       => $otherLead->id,
    ]);

    // Act
    $this->actingAs($admin, 'user');
    $response = get('/admin/operational-dashboard/queues?queue=frontoffice');

    $response->assertOk();

    $ids = getDatagridIds($response);

    expect($ids)->toContain($frontofficeActivity->id)
        ->and($ids)->not()->toContain($otherActivity->id);
});

it('computes open_and_overdue_counts_per_queue_consistently_with_filters', function () {
    // Arrange
    Carbon::setTestNow(Carbon::create(2026, 3, 10, 12));

    $adminRole = Role::factory()->create([
        'permission_type' => 'all',
        'permissions'     => null,
    ]);

    $admin = User::factory()->create([
        'role_id'         => $adminRole->id,
        'view_permission' => 'global',
        'status'          => 1,
    ]);

    /** @var Group $group */
    $group = Group::query()->firstOrFail();

    // Queue: our-tasks (Onze openstaande taken)
    // One overdue task (yesterday), one future task (tomorrow), one done task (ignored).
    Activity::create([
        'type'          => ActivityType::TASK->value,
        'user_id'       => $admin->id,
        'title'         => 'Overdue task',
        'schedule_from' => now()->subDays(2),
        'schedule_to'   => now()->subDay(), // 9-3-2026 (overdue)
        'is_done'       => 0,
        'group_id'      => $group->id,
    ]);

    Activity::create([
        'type'          => ActivityType::TASK->value,
        'user_id'       => $admin->id,
        'title'         => 'Future task',
        'schedule_from' => now(),
        'schedule_to'   => now()->addDay(), // 11-3-2026
        'is_done'       => 0,
        'group_id'      => $group->id,
    ]);

    Activity::create([
        'type'          => ActivityType::TASK->value,
        'user_id'       => $admin->id,
        'title'         => 'Completed task',
        'schedule_from' => now()->subDays(3),
        'schedule_to'   => now()->subDays(2),
        'is_done'       => 1,
        'group_id'      => $group->id,
    ]);

    // Act
    $this->actingAs($admin, 'user');
    $response = get(route('admin.operational-dashboard.index'));

    $response->assertOk()
        ->assertViewHas('queues');

    /** @var array<int, array{key:string,open:int,overdue:int}> $queues */
    $queues = $response->viewData('queues');

    $ourTasks = collect($queues)->firstWhere('key', 'our-tasks');

    expect($ourTasks)->not()->toBeNull();

    // 2 open tasks (overdue + future), 1 of them overdue.
    expect($ourTasks['open'])->toBe(2)
        ->and($ourTasks['overdue'])->toBe(1);

    // Sanity check: repository and registry agree with the same numbers.
    /** @var ActivityQueueRegistry $registry */
    $registry = app(ActivityQueueRegistry::class);
    /** @var ActivityQueueRepository $repo */
    $repo = app(ActivityQueueRepository::class);

    $def = $registry->get('our-tasks');
    expect($def['label'])->toBe('Onze openstaande taken');

    $counts = $repo->counts('our-tasks');
    expect($counts['open'])->toBe(2)
        ->and($counts['overdue'])->toBe(1);
});
