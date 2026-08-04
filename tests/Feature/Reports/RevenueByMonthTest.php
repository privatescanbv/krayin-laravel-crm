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

test('revenue by month data includes orders per status group for drill-down', function () {
    $month = now()->startOfMonth();

    $optionOrder = Order::factory()->create([
        'order_number'      => 'OPT-001',
        'title'             => 'Option order',
        'total_price'       => 100.00,
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
        'created_at'        => $month->copy()->addDays(2),
        'updated_at'        => $month->copy()->addDays(2),
    ]);

    $nearlyWonOrder = Order::factory()->create([
        'order_number'      => 'NW-001',
        'title'             => 'Nearly won order',
        'total_price'       => 250.00,
        'pipeline_stage_id' => PipelineStage::ORDER_BEVESTIGD->id(),
        'created_at'        => $month->copy()->addDays(5),
        'updated_at'        => $month->copy()->addDays(5),
    ]);

    $wonOrder = Order::factory()->create([
        'order_number'      => 'WON-001',
        'title'             => 'Won order',
        'total_price'       => 400.00,
        'pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id(),
        'created_at'        => $month->copy()->addDays(8),
        'updated_at'        => $month->copy()->addDays(8),
    ]);

    $lostOrder = Order::factory()->create([
        'order_number'      => 'LOST-001',
        'title'             => 'Lost order',
        'total_price'       => 50.00,
        'pipeline_stage_id' => PipelineStage::ORDER_VERLOREN->id(),
        'created_at'        => $month->copy()->addDays(3),
        'updated_at'        => $month->copy()->addDays(3),
    ]);

    $response = $this->getJson(route('admin.reports.revenue-by-month.data', [
        'from'         => $month->format('Y-m'),
        'to'           => $month->format('Y-m'),
        'groups'       => ['option', 'nearly_won', 'won', 'lost'],
        'departments'  => ['privatescan'],
    ]));

    $response->assertOk();

    $monthKey = $month->format('Y-m');
    $row = collect($response->json('months_data'))->firstWhere('key', $monthKey);

    expect($row)->not->toBeNull()
        ->and($row['option'])->toEqual(100.0)
        ->and($row['nearly_won'])->toEqual(250.0)
        ->and($row['won'])->toEqual(400.0)
        ->and($row['lost'])->toEqual(50.0)
        ->and($row['total'])->toEqual(800.0)
        ->and($row['orders'])->toHaveKeys(['option', 'nearly_won', 'won', 'lost', 'all']);

    expect(collect($row['orders']['option'])->pluck('id')->all())->toContain($optionOrder->id);
    expect(collect($row['orders']['nearly_won'])->pluck('id')->all())->toContain($nearlyWonOrder->id);
    expect(collect($row['orders']['won'])->pluck('id')->all())->toContain($wonOrder->id);
    expect(collect($row['orders']['lost'])->pluck('id')->all())->toContain($lostOrder->id);
    expect(collect($row['orders']['all'])->pluck('id')->all())
        ->toContain($optionOrder->id, $nearlyWonOrder->id, $wonOrder->id, $lostOrder->id);

    $optionPayload = collect($row['orders']['option'])->firstWhere('id', $optionOrder->id);

    expect($optionPayload)
        ->toHaveKeys(['id', 'label', 'url', 'created_at', 'stage', 'group', 'total_price', 'inkoop_price'])
        ->and($optionPayload['label'])->toBe('OPT-001')
        ->and($optionPayload['group'])->toBe('option')
        ->and($optionPayload['total_price'])->toEqual(100.0)
        ->and($optionPayload['url'])->toContain((string) $optionOrder->id);
});

test('revenue by month total only sums selected groups', function () {
    $month = now()->startOfMonth();

    Order::factory()->create([
        'total_price'       => 100.00,
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
        'created_at'        => $month->copy()->addDay(),
        'updated_at'        => $month->copy()->addDay(),
    ]);

    Order::factory()->create([
        'total_price'       => 400.00,
        'pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id(),
        'created_at'        => $month->copy()->addDays(2),
        'updated_at'        => $month->copy()->addDays(2),
    ]);

    Order::factory()->create([
        'total_price'       => 50.00,
        'pipeline_stage_id' => PipelineStage::ORDER_VERLOREN->id(),
        'created_at'        => $month->copy()->addDays(3),
        'updated_at'        => $month->copy()->addDays(3),
    ]);

    $response = $this->getJson(route('admin.reports.revenue-by-month.data', [
        'from'        => $month->format('Y-m'),
        'to'          => $month->format('Y-m'),
        'groups'      => ['option', 'won'],
        'departments' => ['privatescan'],
    ]));

    $response->assertOk();

    $row = collect($response->json('months_data'))->firstWhere('key', $month->format('Y-m'));

    expect($row['total'])->toEqual(500.0)
        ->and($row['orders'])->toHaveKeys(['option', 'won', 'all'])
        ->and($row['orders'])->not->toHaveKey('lost')
        ->and(collect($row['orders']['all']))->toHaveCount(2);
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
    expect($hernia['won'])->toEqual(400.0)
        ->and(collect($hernia['orders']['won'])->pluck('id')->all())
        ->toContain($herniaOrder->id)
        ->not->toContain($privatescanOrder->id);

    $privatescan = $rowFor([Departments::PRIVATESCAN->key()]);
    expect($privatescan['won'])->toEqual(100.0)
        ->and(collect($privatescan['orders']['won'])->pluck('id')->all())
        ->toContain($privatescanOrder->id)
        ->not->toContain($herniaOrder->id);

    // Onbekende sleutel valt terug op alle afdelingen.
    expect($rowFor(['hernia'])['won'])->toEqual(500.0);
});

test('revenue by month index page loads', function () {
    $this->get(route('admin.reports.revenue-by-month.index'))
        ->assertOk()
        ->assertSee('Omzet per maand')
        ->assertSee('Omzet netto')
        ->assertSee('Omzet bruto');
});
