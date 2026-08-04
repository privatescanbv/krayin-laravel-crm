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

test('revenue by month always loads lost for netto even when lost filter is off', function () {
    $month = now()->startOfMonth();

    Order::factory()->create([
        'order_number'      => 'OPT-001',
        'total_price'       => 100.00,
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
        'created_at'        => $month->copy()->addDays(2),
        'updated_at'        => $month->copy()->addDays(2),
    ]);

    Order::factory()->create([
        'order_number'      => 'WON-001',
        'total_price'       => 400.00,
        'pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id(),
        'created_at'        => $month->copy()->addDays(8),
        'updated_at'        => $month->copy()->addDays(8),
    ]);

    $lostOrder = Order::factory()->create([
        'order_number'      => 'LOST-001',
        'total_price'       => 50.00,
        'pipeline_stage_id' => PipelineStage::ORDER_VERLOREN->id(),
        'created_at'        => $month->copy()->addDays(3),
        'updated_at'        => $month->copy()->addDays(3),
    ]);

    $response = $this->getJson(route('admin.reports.revenue-by-month.data', [
        'from'        => $month->format('Y-m'),
        'to'          => $month->format('Y-m'),
        'groups'      => ['option', 'nearly_won', 'won'],
        'departments' => ['privatescan'],
    ]));

    $response->assertOk();

    $row = collect($response->json('months_data'))->firstWhere('key', $month->format('Y-m'));

    expect($row)->not->toBeNull()
        ->and($row['verloren'])->toEqual(50.0)
        ->and($row['bruto'])->toEqual(550.0)
        ->and($row['netto'])->toEqual(500.0)
        ->and($row['orders'])->toHaveKey('lost')
        ->and(collect($row['orders']['lost'])->pluck('id')->all())->toContain($lostOrder->id);

    // Chart datasets should not include lost when filter is off.
    expect(collect($response->json('datasets'))->pluck('group')->all())
        ->not->toContain('lost');
});

test('revenue by month keeps bruto and netto stable when toggling lost filter', function () {
    $month = now()->startOfMonth();

    Order::factory()->create([
        'total_price'       => 100.00,
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
        'created_at'        => $month->copy()->addDay(),
        'updated_at'        => $month->copy()->addDay(),
    ]);

    Order::factory()->create([
        'total_price'       => 50.00,
        'pipeline_stage_id' => PipelineStage::ORDER_VERLOREN->id(),
        'created_at'        => $month->copy()->addDays(2),
        'updated_at'        => $month->copy()->addDays(2),
    ]);

    $withoutLost = $this->getJson(route('admin.reports.revenue-by-month.data', [
        'from'        => $month->format('Y-m'),
        'to'          => $month->format('Y-m'),
        'groups'      => ['option', 'won'],
        'departments' => ['privatescan'],
    ]))->json('months_data.0');

    $withLost = $this->getJson(route('admin.reports.revenue-by-month.data', [
        'from'        => $month->format('Y-m'),
        'to'          => $month->format('Y-m'),
        'groups'      => ['option', 'won', 'lost'],
        'departments' => ['privatescan'],
    ]))->json('months_data.0');

    expect($withoutLost['bruto'])->toEqual($withLost['bruto'])
        ->and($withoutLost['netto'])->toEqual($withLost['netto'])
        ->and($withoutLost['verloren'])->toEqual($withLost['verloren'])
        ->and($withoutLost['bruto'])->toEqual(150.0)
        ->and($withoutLost['netto'])->toEqual(100.0);
});

test('revenue by month filters on the departments enum key', function () {
    $month = now()->startOfMonth();

    $privatescanOrder = Order::factory()->create([
        'total_price'       => 100.00,
        'pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id(),
        'created_at'        => $month->copy()->addDay(),
        'updated_at'        => $month->copy()->addDay(),
    ]);

    $herniaOrder = Order::factory()->create([
        'total_price'       => 400.00,
        'pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN_HERNIA->id(),
        'created_at'        => $month->copy()->addDays(2),
        'updated_at'        => $month->copy()->addDays(2),
    ]);

    $rowFor = function (array $departments) use ($month) {
        $response = $this->getJson(route('admin.reports.revenue-by-month.data', [
            'from'        => $month->format('Y-m'),
            'to'          => $month->format('Y-m'),
            'groups'      => ['won'],
            'departments' => $departments,
        ]));

        $response->assertOk();

        return collect($response->json('months_data'))->firstWhere('key', $month->format('Y-m'));
    };

    $hernia = $rowFor([Departments::HERNIA->key()]);
    expect($hernia['bruto'])->toEqual(400.0)
        ->and(collect($hernia['orders']['won'])->pluck('id')->all())
        ->toContain($herniaOrder->id)
        ->not->toContain($privatescanOrder->id);

    $privatescan = $rowFor([Departments::PRIVATESCAN->key()]);
    expect($privatescan['bruto'])->toEqual(100.0)
        ->and(collect($privatescan['orders']['won'])->pluck('id')->all())
        ->toContain($privatescanOrder->id)
        ->not->toContain($herniaOrder->id);
});

test('revenue by month index page loads with filter-linked status columns', function () {
    $this->get(route('admin.reports.revenue-by-month.index'))
        ->assertOk()
        ->assertSee('Omzet per maand')
        ->assertSee('Omzet bruto')
        ->assertSee('>Option</th>', false)
        ->assertSee('>Bijna gewonnen</th>', false)
        ->assertSee('>Gewonnen</th>', false)
        ->assertSee('>Verloren</th>', false)
        ->assertSee('>Inkoop</th>', false)
        ->assertSee('Omzet netto')
        ->assertSee("selectedGroups.includes('option')", false)
        ->assertSee("selectedGroups.includes('nearly_won')", false)
        ->assertSee("selectedGroups.includes('won')", false)
        ->assertSee("selectedGroups.includes('lost')", false)
        ->assertSee('Omzet bruto minus verloren');
});
