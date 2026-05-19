<?php

use App\Enums\ActivityType;
use App\Enums\Departments;
use App\Models\Department;
use App\Models\Order;
use App\Models\SalesLead;
use App\Services\ActivityQueueRepository;
use Database\Seeders\TestSeeder;
use Webkul\Activity\Models\Activity;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Group;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

use function Pest\Laravel\get;

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

function createGlobalAdmin(): User
{
    $adminRole = Role::factory()->create([
        'permission_type' => 'all',
        'permissions'     => null,
    ]);

    return User::factory()->create([
        'role_id'         => $adminRole->id,
        'view_permission' => 'global',
        'status'          => 1,
    ]);
}

function groupForDepartment(string $departmentName): Group
{
    $department = Department::where('name', $departmentName)->firstOrFail();

    return Group::where('department_id', $department->id)->firstOrFail();
}

it('filters our-tasks by activity user group department not lead department', function () {
    $admin = createGlobalAdmin();

    $privatescanDepartment = Department::where('name', Departments::PRIVATESCAN->value)->firstOrFail();
    $herniaGroup = groupForDepartment(Departments::HERNIA->value);

    $lead = Lead::factory()->create(['department_id' => $privatescanDepartment->id]);

    $leadTask = Activity::create([
        'type'          => ActivityType::TASK->value,
        'user_id'       => $admin->id,
        'title'         => 'Lead-only task',
        'schedule_from' => now(),
        'schedule_to'   => now()->addDay(),
        'is_done'       => 0,
        'group_id'      => $herniaGroup->id,
        'lead_id'       => $lead->id,
    ]);

    $this->actingAs($admin, 'user');

    /** @var ActivityQueueRepository $repo */
    $repo = app(ActivityQueueRepository::class);

    expect($repo->counts('our-tasks', Departments::PRIVATESCAN->value)['open'])->toBe(0)
        ->and($repo->counts('our-tasks', Departments::HERNIA->value)['open'])->toBe(1);

    $herniaIds = getDatagridIds(
        get('/admin/operational-dashboard/queues?queue=our-tasks&department='.urlencode(Departments::HERNIA->value))
    );

    expect($herniaIds)->toContain($leadTask->id);
});

it('isolates our-tasks per department by activity group', function () {
    $admin = createGlobalAdmin();
    $privatescanGroup = groupForDepartment(Departments::PRIVATESCAN->value);
    $herniaGroup = groupForDepartment(Departments::HERNIA->value);

    $privatescanSalesLead = SalesLead::factory()->create([
        'department_id' => Department::where('name', Departments::PRIVATESCAN->value)->firstOrFail()->id,
    ]);
    $herniaSalesLead = SalesLead::factory()->create([
        'department_id' => Department::where('name', Departments::HERNIA->value)->firstOrFail()->id,
    ]);

    $privatescanTask = Activity::create([
        'type'          => ActivityType::TASK->value,
        'user_id'       => $admin->id,
        'title'         => 'Privatescan sales task',
        'schedule_from' => now(),
        'schedule_to'   => now()->addDay(),
        'is_done'       => 0,
        'group_id'      => $privatescanGroup->id,
        'sales_lead_id' => $privatescanSalesLead->id,
    ]);

    $herniaTask = Activity::create([
        'type'          => ActivityType::TASK->value,
        'user_id'       => $admin->id,
        'title'         => 'Hernia sales task',
        'schedule_from' => now(),
        'schedule_to'   => now()->addDay(),
        'is_done'       => 0,
        'group_id'      => $herniaGroup->id,
        'sales_lead_id' => $herniaSalesLead->id,
    ]);

    $this->actingAs($admin, 'user');

    /** @var ActivityQueueRepository $repo */
    $repo = app(ActivityQueueRepository::class);

    $privatescanIds = getDatagridIds(
        get('/admin/operational-dashboard/queues?queue=our-tasks&department='.urlencode(Departments::PRIVATESCAN->value))
    );
    $herniaIds = getDatagridIds(
        get('/admin/operational-dashboard/queues?queue=our-tasks&department='.urlencode(Departments::HERNIA->value))
    );

    expect($repo->counts('our-tasks', Departments::PRIVATESCAN->value)['open'])->toBe(1)
        ->and($repo->counts('our-tasks', Departments::HERNIA->value)['open'])->toBe(1)
        ->and($privatescanIds)->toContain($privatescanTask->id)
        ->and($privatescanIds)->not->toContain($herniaTask->id)
        ->and($herniaIds)->toContain($herniaTask->id)
        ->and($herniaIds)->not->toContain($privatescanTask->id);
});

it('excludes completed tasks and non-task types from our-tasks department filter', function () {
    $admin = createGlobalAdmin();
    $privatescanGroup = groupForDepartment(Departments::PRIVATESCAN->value);

    $salesLead = SalesLead::factory()->create([
        'department_id' => Department::where('name', Departments::PRIVATESCAN->value)->firstOrFail()->id,
    ]);

    $openTask = Activity::create([
        'type'          => ActivityType::TASK->value,
        'user_id'       => $admin->id,
        'title'         => 'Open task',
        'schedule_from' => now(),
        'schedule_to'   => now()->addDay(),
        'is_done'       => 0,
        'group_id'      => $privatescanGroup->id,
        'sales_lead_id' => $salesLead->id,
    ]);

    Activity::create([
        'type'          => ActivityType::TASK->value,
        'user_id'       => $admin->id,
        'title'         => 'Done task',
        'schedule_from' => now(),
        'schedule_to'   => now()->addDay(),
        'is_done'       => 1,
        'group_id'      => $privatescanGroup->id,
        'sales_lead_id' => $salesLead->id,
    ]);

    Activity::create([
        'type'          => ActivityType::CALL->value,
        'user_id'       => $admin->id,
        'title'         => 'Open call',
        'schedule_from' => now(),
        'schedule_to'   => now()->addDay(),
        'is_done'       => 0,
        'group_id'      => $privatescanGroup->id,
        'sales_lead_id' => $salesLead->id,
    ]);

    $this->actingAs($admin, 'user');

    /** @var ActivityQueueRepository $repo */
    $repo = app(ActivityQueueRepository::class);

    expect($repo->counts('our-tasks', Departments::PRIVATESCAN->value)['open'])->toBe(1);

    $ids = getDatagridIds(
        get('/admin/operational-dashboard/queues?queue=our-tasks&department='.urlencode(Departments::PRIVATESCAN->value))
    );

    expect($ids)->toContain($openTask->id)
        ->and($ids)->toHaveCount(1);
});

it('filters my-tasks by activity group department and assigned user', function () {
    $admin = createGlobalAdmin();
    $otherUser = createGlobalAdmin();

    $privatescanGroup = groupForDepartment(Departments::PRIVATESCAN->value);
    $herniaGroup = groupForDepartment(Departments::HERNIA->value);

    $myPrivatescanTask = Activity::create([
        'type'          => ActivityType::TASK->value,
        'user_id'       => $admin->id,
        'title'         => 'My Privatescan task',
        'schedule_from' => now(),
        'schedule_to'   => now()->addDay(),
        'is_done'       => 0,
        'group_id'      => $privatescanGroup->id,
    ]);

    Activity::create([
        'type'          => ActivityType::TASK->value,
        'user_id'       => $admin->id,
        'title'         => 'My Hernia task',
        'schedule_from' => now(),
        'schedule_to'   => now()->addDay(),
        'is_done'       => 0,
        'group_id'      => $herniaGroup->id,
    ]);

    Activity::create([
        'type'          => ActivityType::TASK->value,
        'user_id'       => $otherUser->id,
        'title'         => 'Other user Privatescan task',
        'schedule_from' => now(),
        'schedule_to'   => now()->addDay(),
        'is_done'       => 0,
        'group_id'      => $privatescanGroup->id,
    ]);

    $this->actingAs($admin, 'user');

    /** @var ActivityQueueRepository $repo */
    $repo = app(ActivityQueueRepository::class);

    expect($repo->counts('my-tasks', Departments::PRIVATESCAN->value)['open'])->toBe(1)
        ->and($repo->counts('my-tasks', Departments::HERNIA->value)['open'])->toBe(1);

    $ids = getDatagridIds(
        get('/admin/operational-dashboard/queues?queue=my-tasks&department='.urlencode(Departments::PRIVATESCAN->value))
    );

    expect($ids)->toContain($myPrivatescanTask->id)
        ->and($ids)->toHaveCount(1);
});

it('excludes calls from my-tasks even with matching group', function () {
    $admin = createGlobalAdmin();
    $herniaGroup = groupForDepartment(Departments::HERNIA->value);

    $call = Activity::create([
        'type'          => ActivityType::CALL->value,
        'user_id'       => $admin->id,
        'title'         => 'My call',
        'schedule_from' => now(),
        'schedule_to'   => now()->addDay(),
        'is_done'       => 0,
        'group_id'      => $herniaGroup->id,
    ]);

    $task = Activity::create([
        'type'          => ActivityType::TASK->value,
        'user_id'       => $admin->id,
        'title'         => 'My task',
        'schedule_from' => now(),
        'schedule_to'   => now()->addDay(),
        'is_done'       => 0,
        'group_id'      => $herniaGroup->id,
    ]);

    $this->actingAs($admin, 'user');

    $ids = getDatagridIds(
        get('/admin/operational-dashboard/queues?queue=my-tasks&department='.urlencode(Departments::HERNIA->value))
    );

    expect($ids)->toContain($task->id)
        ->and($ids)->not->toContain($call->id);
});

it('returns department-scoped counts from the operational dashboard counts endpoint', function () {
    $admin = createGlobalAdmin();
    $privatescanGroup = groupForDepartment(Departments::PRIVATESCAN->value);
    $herniaGroup = groupForDepartment(Departments::HERNIA->value);

    Activity::create([
        'type'          => ActivityType::TASK->value,
        'user_id'       => $admin->id,
        'title'         => 'Privatescan task',
        'schedule_from' => now()->subDay(),
        'schedule_to'   => now()->subDay(),
        'is_done'       => 0,
        'group_id'      => $privatescanGroup->id,
    ]);

    Activity::create([
        'type'          => ActivityType::TASK->value,
        'user_id'       => $admin->id,
        'title'         => 'Hernia task',
        'schedule_from' => now(),
        'schedule_to'   => now()->addDay(),
        'is_done'       => 0,
        'group_id'      => $herniaGroup->id,
    ]);

    $this->actingAs($admin, 'user');

    /** @var ActivityQueueRepository $repo */
    $repo = app(ActivityQueueRepository::class);

    $response = get('/admin/operational-dashboard/counts?department='.urlencode(Departments::PRIVATESCAN->value));
    $response->assertOk();

    $ourTasksFromApi = collect($response->json())->firstWhere('key', 'our-tasks');

    expect($ourTasksFromApi)->not()->toBeNull()
        ->and($ourTasksFromApi['open'])->toBe($repo->counts('our-tasks', Departments::PRIVATESCAN->value)['open'])
        ->and($ourTasksFromApi['overdue'])->toBe($repo->counts('our-tasks', Departments::PRIVATESCAN->value)['overdue'])
        ->and($ourTasksFromApi['open'])->toBe(1)
        ->and($ourTasksFromApi['overdue'])->toBe(1);
});

it('filters order tasks by activity group not saleslead department', function () {
    $admin = createGlobalAdmin();
    $herniaGroup = groupForDepartment(Departments::HERNIA->value);

    $privatescanDepartment = Department::where('name', Departments::PRIVATESCAN->value)->firstOrFail();
    $herniaDepartment = Department::where('name', Departments::HERNIA->value)->firstOrFail();

    $lead = Lead::factory()->create(['department_id' => $herniaDepartment->id]);
    $salesLead = SalesLead::factory()->create([
        'lead_id'       => $lead->id,
        'department_id' => $privatescanDepartment->id,
    ]);
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    $orderTask = Activity::create([
        'type'          => ActivityType::TASK->value,
        'user_id'       => $admin->id,
        'title'         => 'Order-only linked task',
        'schedule_from' => now(),
        'schedule_to'   => now()->addDay(),
        'is_done'       => 0,
        'group_id'      => $herniaGroup->id,
        'order_id'      => $order->id,
    ]);

    $this->actingAs($admin, 'user');

    $privatescanIds = getDatagridIds(
        get('/admin/operational-dashboard/queues?queue=our-tasks&department='.urlencode(Departments::PRIVATESCAN->value))
    );
    $herniaIds = getDatagridIds(
        get('/admin/operational-dashboard/queues?queue=our-tasks&department='.urlencode(Departments::HERNIA->value))
    );

    expect($herniaIds)->toContain($orderTask->id)
        ->and($privatescanIds)->not->toContain($orderTask->id);
});

it('shows my-tasks for order activity with hernia group on privatescan saleslead', function () {
    $admin = createGlobalAdmin();
    $herniaGroup = groupForDepartment(Departments::HERNIA->value);
    $privatescanDepartment = Department::where('name', Departments::PRIVATESCAN->value)->firstOrFail();

    $salesLead = SalesLead::factory()->create(['department_id' => $privatescanDepartment->id]);
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    $task = Activity::create([
        'type'          => ActivityType::TASK->value,
        'user_id'       => $admin->id,
        'title'         => 'Order task with Hernia group',
        'schedule_from' => now(),
        'schedule_to'   => now()->addDay(),
        'is_done'       => 0,
        'group_id'      => $herniaGroup->id,
        'order_id'      => $order->id,
    ]);

    $this->actingAs($admin, 'user');

    $ids = getDatagridIds(
        get('/admin/operational-dashboard/queues?queue=my-tasks&department='.urlencode(Departments::HERNIA->value))
    );

    expect($ids)->toContain($task->id);
});
