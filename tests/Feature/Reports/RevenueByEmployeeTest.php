<?php

use App\Enums\Departments;
use App\Enums\PipelineStage;
use App\Models\Order;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    $role = Role::factory()->create([
        'permission_type' => 'all',
        'permissions'     => null,
    ]);

    $this->admin = User::factory()->create([
        'status'  => 1,
        'role_id' => $role->id,
    ]);

    $this->actingAs($this->admin, 'user');
    $this->withoutMiddleware(Authenticate::class);
});

test('revenue by employee always loads lost for netto even when lost filter is off', function () {
    $weekStart = now()->startOfWeek();

    Order::factory()->create([
        'user_id'           => $this->admin->id,
        'order_number'      => 'EMP-OPT-001',
        'total_price'       => 100.00,
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
        'created_at'        => $weekStart->copy()->addDay(),
        'updated_at'        => $weekStart->copy()->addDay(),
    ]);

    Order::factory()->create([
        'user_id'           => $this->admin->id,
        'order_number'      => 'EMP-WON-001',
        'total_price'       => 400.00,
        'pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id(),
        'created_at'        => $weekStart->copy()->addDays(2),
        'updated_at'        => $weekStart->copy()->addDays(2),
    ]);

    Order::factory()->create([
        'user_id'           => $this->admin->id,
        'order_number'      => 'EMP-LOST-001',
        'total_price'       => 50.00,
        'pipeline_stage_id' => PipelineStage::ORDER_VERLOREN->id(),
        'created_at'        => $weekStart->copy()->addDays(3),
        'updated_at'        => $weekStart->copy()->addDays(3),
    ]);

    $response = $this->getJson(route('admin.reports.revenue-by-employee.data', [
        'period'      => 'week',
        'week'        => $weekStart->isoWeek(),
        'year'        => $weekStart->isoWeekYear(),
        'groups'      => ['option', 'nearly_won', 'won'],
        'departments' => ['privatescan'],
    ]));

    $response->assertOk();

    $employee = collect($response->json('employees'))->firstWhere('user_id', $this->admin->id);

    expect($employee)->not->toBeNull()
        ->and($employee['bruto'])->toEqual(550.0)
        ->and($employee['verloren'])->toEqual(50.0)
        ->and($employee['netto'])->toEqual(500.0)
        ->and(collect($employee['orders'])->where('is_lost', true))->toHaveCount(1);
});

test('revenue by employee keeps bruto and netto stable when toggling lost filter', function () {
    $weekStart = now()->startOfWeek();

    Order::factory()->create([
        'user_id'           => $this->admin->id,
        'total_price'       => 100.00,
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
        'created_at'        => $weekStart->copy()->addDay(),
        'updated_at'        => $weekStart->copy()->addDay(),
    ]);

    Order::factory()->create([
        'user_id'           => $this->admin->id,
        'total_price'       => 50.00,
        'pipeline_stage_id' => PipelineStage::ORDER_VERLOREN->id(),
        'created_at'        => $weekStart->copy()->addDays(2),
        'updated_at'        => $weekStart->copy()->addDays(2),
    ]);

    $params = [
        'period'      => 'week',
        'week'        => $weekStart->isoWeek(),
        'year'        => $weekStart->isoWeekYear(),
        'departments' => ['privatescan'],
    ];

    $withoutLost = collect($this->getJson(route('admin.reports.revenue-by-employee.data', [
        ...$params,
        'groups' => ['option', 'won'],
    ]))->json('employees'))->firstWhere('user_id', $this->admin->id);

    $withLost = collect($this->getJson(route('admin.reports.revenue-by-employee.data', [
        ...$params,
        'groups' => ['option', 'won', 'lost'],
    ]))->json('employees'))->firstWhere('user_id', $this->admin->id);

    expect($withoutLost['bruto'])->toEqual($withLost['bruto'])
        ->and($withoutLost['netto'])->toEqual($withLost['netto'])
        ->and($withoutLost['verloren'])->toEqual($withLost['verloren'])
        ->and($withoutLost['bruto'])->toEqual(150.0)
        ->and($withoutLost['netto'])->toEqual(100.0);
});

test('revenue by employee month period aggregates full month days', function () {
    $monthStart = now()->startOfMonth();
    $monthKey = $monthStart->format('Y-m');

    Order::factory()->create([
        'user_id'           => $this->admin->id,
        'order_number'      => 'EMP-MONTH-OPT',
        'total_price'       => 150.00,
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
        'created_at'        => $monthStart->copy()->addDays(1),
        'updated_at'        => $monthStart->copy()->addDays(1),
    ]);

    Order::factory()->create([
        'user_id'           => $this->admin->id,
        'order_number'      => 'EMP-MONTH-WON',
        'total_price'       => 350.00,
        'pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id(),
        'created_at'        => $monthStart->copy()->addDays(10),
        'updated_at'        => $monthStart->copy()->addDays(10),
    ]);

    Order::factory()->create([
        'user_id'           => $this->admin->id,
        'total_price'       => 999.00,
        'pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id(),
        'created_at'        => $monthStart->copy()->subMonth()->addDays(5),
        'updated_at'        => $monthStart->copy()->subMonth()->addDays(5),
    ]);

    $response = $this->getJson(route('admin.reports.revenue-by-employee.data', [
        'period'      => 'month',
        'month'       => $monthKey,
        'groups'      => ['option', 'won'],
        'departments' => ['privatescan'],
    ]));

    $response->assertOk();

    $employee = collect($response->json('employees'))->firstWhere('user_id', $this->admin->id);

    expect($response->json('period'))->toBe('month')
        ->and($response->json('days'))->toHaveCount($monthStart->daysInMonth)
        ->and($employee['bruto'])->toEqual(500.0)
        ->and($employee['verloren'])->toEqual(0.0)
        ->and($employee['netto'])->toEqual(500.0);
});

test('revenue by employee filter options expose groups and departments', function () {
    $response = $this->getJson(route('admin.reports.revenue-by-employee.filter-options'));

    $response->assertOk();

    expect($response->json('departments'))->toBe([
        ['id' => Departments::PRIVATESCAN->key(), 'label' => Departments::PRIVATESCAN->value],
        ['id' => Departments::HERNIA->key(), 'label' => Departments::HERNIA->value],
    ]);

    expect(collect($response->json('groups'))->pluck('id')->all())
        ->toBe(['option', 'nearly_won', 'won', 'lost']);
});

test('revenue by employee filters on the departments enum key', function () {
    $weekStart = now()->startOfWeek();

    $privatescanOrder = Order::factory()->create([
        'user_id'           => $this->admin->id,
        'total_price'       => 100.00,
        'pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id(),
        'created_at'        => $weekStart->copy()->addDay(),
        'updated_at'        => $weekStart->copy()->addDay(),
    ]);

    $herniaOrder = Order::factory()->create([
        'user_id'           => $this->admin->id,
        'total_price'       => 400.00,
        'pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN_HERNIA->id(),
        'created_at'        => $weekStart->copy()->addDays(2),
        'updated_at'        => $weekStart->copy()->addDays(2),
    ]);

    $employeeFor = function (array $departments) use ($weekStart) {
        $response = $this->getJson(route('admin.reports.revenue-by-employee.data', [
            'period'      => 'week',
            'week'        => $weekStart->isoWeek(),
            'year'        => $weekStart->isoWeekYear(),
            'groups'      => ['won'],
            'departments' => $departments,
        ]));

        $response->assertOk();

        return collect($response->json('employees'))->firstWhere('user_id', $this->admin->id);
    };

    $hernia = $employeeFor([Departments::HERNIA->key()]);
    expect($hernia['bruto'])->toEqual(400.0)
        ->and(collect($hernia['orders'])->pluck('id')->all())
        ->toContain($herniaOrder->id)
        ->not->toContain($privatescanOrder->id);

    $privatescan = $employeeFor([Departments::PRIVATESCAN->key()]);
    expect($privatescan['bruto'])->toEqual(100.0)
        ->and(collect($privatescan['orders'])->pluck('id')->all())
        ->toContain($privatescanOrder->id)
        ->not->toContain($herniaOrder->id);
});

test('revenue by employee index page hides verloren column via filter condition', function () {
    $this->get(route('admin.reports.revenue-by-employee.index'))
        ->assertOk()
        ->assertSee('Omzet bruto')
        ->assertSee('Verloren')
        ->assertSee('Omzet netto')
        ->assertSee("selectedGroups.includes('lost')", false)
        ->assertSee('Omzet bruto minus verloren');
});
