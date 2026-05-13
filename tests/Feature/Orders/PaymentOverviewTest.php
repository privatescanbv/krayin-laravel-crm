<?php

use App\Enums\PipelineType;
use App\Models\Order;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    $this->user = User::factory()->create(['name' => 'Admin Payment Overview']);
    $this->actingAs($this->user, 'user');
    $this->withoutMiddleware(Authenticate::class);

    $this->pipeline = Pipeline::factory()->create([
        'name'        => 'Order Pipeline Payment Overview',
        'type'        => PipelineType::ORDER,
        'is_default'  => 0,
        'rotten_days' => 0,
    ]);

    $this->stageOpen = Stage::factory()->create([
        'lead_pipeline_id' => $this->pipeline->id,
        'name'             => 'Open',
        'code'             => 'order_payment_open',
        'sort_order'       => 1,
        'is_won'           => false,
        'is_lost'          => false,
    ]);

    $this->stageLost = Stage::factory()->create([
        'lead_pipeline_id' => $this->pipeline->id,
        'name'             => 'Verloren',
        'code'             => 'order_payment_lost',
        'sort_order'       => 2,
        'is_won'           => false,
        'is_lost'          => true,
    ]);
});

test('payment overview excludes orders in verloren pipeline stage', function () {
    $suffix = uniqid('', true);

    $orderOpen = Order::factory()->create([
        'pipeline_stage_id'   => $this->stageOpen->id,
        'title'               => 'Betaaloverzicht open order '.$suffix,
        'total_price'         => 100.00,
        'first_examination_at'=> now(),
    ]);

    $orderLost = Order::factory()->create([
        'pipeline_stage_id'   => $this->stageLost->id,
        'title'               => 'Betaaloverzicht verloren order '.$suffix,
        'total_price'         => 100.00,
        'first_examination_at'=> now(),
    ]);

    $response = $this->get(route('admin.orders.payment-overview', [
        'pipeline_id' => $this->pipeline->id,
    ]));

    $response->assertOk();
    $response->assertSee($orderOpen->title, false);
    $response->assertDontSee($orderLost->title, false);
});
