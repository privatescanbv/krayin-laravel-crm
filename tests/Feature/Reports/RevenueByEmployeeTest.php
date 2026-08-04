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

test('revenue by employee separates omzet netto and omzet bruto', function () {
    $weekStart = now()->startOfWeek();

    $optionOrder = Order::factory()->create([
        'user_id'           => $this->admin->id,
        'order_number'      => 'EMP-OPT-001',
        'total_price'       => 100.00,
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
        'created_at'        => $weekStart->copy()->addDay(),
        'updated_at'        => $weekStart->copy()->addDay(),
    ]);

    $wonOrder = Order::factory()->create([
        'user_id'           => $this->admin->id,
        'order_number'      => 'EMP-WON-001',
        'total_price'       => 400.00,
        'pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id(),
        'created_at'        => $weekStart->copy()->addDays(2),
        'updated_at'        => $weekStart->copy()->addDays(2),
    ]);

    $response = $this->getJson(route('admin.reports.revenue-by-employee.data', [
        'period'      => 'week',
        'week'        => $weekStart->isoWeek(),
        'year'        => $weekStart->isoWeekYear(),
        'stages'      => [
            PipelineStage::ORDER_CONFIRM->id(),
            PipelineStage::ORDER_GEWONNEN->id(),
        ],
        'departments' => ['privatescan'],
    ]));

    $response->assertOk();

    $employee = collect($response->json('employees'))->firstWhere('user_id', $this->admin->id);

    expect($employee)->not->toBeNull()
        ->and($employee['netto'])->toEqual(400.0)
        ->and($employee['bruto'])->toEqual(500.0)
        ->and($employee)->not->toHaveKey('week_total')
        ->and($employee)->not->toHaveKey('week_bruto');

    $orders = collect($employee['orders']);

    expect($orders->firstWhere('id', $wonOrder->id)['is_won'])->toBeTrue()
        ->and($orders->firstWhere('id', $optionOrder->id)['is_won'])->toBeFalse()
        ->and($response->json('period'))->toBe('week')
        ->and($response->json('days'))->toHaveCount(7)
        ->and($response->json('period_label'))->not->toBeEmpty();

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

    // Outside selected month — must be ignored
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
        'stages'      => [
            PipelineStage::ORDER_CONFIRM->id(),
            PipelineStage::ORDER_GEWONNEN->id(),
        ],
        'departments' => ['privatescan'],
    ]));

    $response->assertOk();

    expect($response->json('period'))->toBe('month')
        ->and($response->json('month'))->toBe($monthKey)
        ->and($response->json('days'))->toHaveCount($monthStart->daysInMonth)
        ->and($response->json('period_label'))->not->toBeEmpty();

    $employee = collect($response->json('employees'))->firstWhere('user_id', $this->admin->id);

    expect($employee)->not->toBeNull()
        ->and($employee['netto'])->toEqual(350.0)
        ->and($employee['bruto'])->toEqual(500.0)
        ->and($employee['orders'])->toHaveCount(2);
});

test('revenue by employee filter options expose the departments enum', function () {
    $response = $this->getJson(route('admin.reports.revenue-by-employee.filter-options'));

    $response->assertOk();

    expect($response->json('departments'))->toBe([
        ['id' => Departments::PRIVATESCAN->key(), 'label' => Departments::PRIVATESCAN->value],
        ['id' => Departments::HERNIA->key(), 'label' => Departments::HERNIA->value],
    ]);

    $stageDepartments = collect($response->json('stages'))->pluck('department')->unique()->sort()->values();

    expect($stageDepartments->all())->toBe([
        Departments::HERNIA->key(),
        Departments::PRIVATESCAN->key(),
    ]);
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

test('revenue by employee index page shows period toggle and omzet labels', function () {
    $this->get(route('admin.reports.revenue-by-employee.index'))
        ->assertOk()
        ->assertSee('Omzet netto')
        ->assertSee('Omzet bruto')
        ->assertSee('Week')
        ->assertSee('Maand')
        ->assertDontSee('Weekomzet');
});
