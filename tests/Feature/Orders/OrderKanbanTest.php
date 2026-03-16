<?php

use App\Enums\LostReason;
use App\Enums\PipelineType;
use App\Models\Order;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    $this->user = User::factory()->create(['name' => 'Admin Tester']);
    $this->actingAs($this->user, 'user');
    $this->withoutMiddleware(Authenticate::class);

    $this->pipeline = Pipeline::factory()->create([
        'name'        => 'Order Pipeline',
        'type'        => PipelineType::ORDER,
        'is_default'  => 0,
        'rotten_days' => 0,
    ]);

    $this->stageNew = Stage::factory()->create([
        'lead_pipeline_id' => $this->pipeline->id,
        'name'             => 'New',
        'code'             => 'order_new',
        'sort_order'       => 1,
        'is_won'           => false,
        'is_lost'          => false,
    ]);

    $this->stageInProgress = Stage::factory()->create([
        'lead_pipeline_id' => $this->pipeline->id,
        'name'             => 'In progress',
        'code'             => 'order_in_progress',
        'sort_order'       => 2,
        'is_won'           => false,
        'is_lost'          => false,
    ]);

    $this->stageWon = Stage::factory()->create([
        'lead_pipeline_id' => $this->pipeline->id,
        'name'             => 'Won',
        'code'             => 'order_won',
        'sort_order'       => 3,
        'is_won'           => true,
        'is_lost'          => false,
    ]);

    $this->stageLost = Stage::factory()->create([
        'lead_pipeline_id' => $this->pipeline->id,
        'name'             => 'Lost',
        'code'             => 'order_lost',
        'sort_order'       => 4,
        'is_won'           => false,
        'is_lost'          => true,
    ]);
});

function getOrderKanban(int $pipelineId, array $params = [])
{
    return test()->getJson(route('admin.orders.get', array_merge([
        'pipeline_id' => $pipelineId,
    ], $params)));
}

test('order kanban endpoint returns stages and orders', function () {
    $o1 = Order::factory()->create([
        'pipeline_stage_id' => $this->stageNew->id,
    ]);

    $o2 = Order::factory()->create([
        'pipeline_stage_id' => $this->stageInProgress->id,
    ]);

    $response = getOrderKanban($this->pipeline->id);
    $response->assertOk();

    $response->assertJsonStructure([
        '*' => [
            'id',
            'name',
            'sort_order',
            'leads' => [
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'total_price',
                        'pipeline_stage_id',
                        'created_at',
                        'open_activities_count',
                        'payment_status_label',
                        'payment_status_badge_class',
                    ],
                ],
                'meta' => [
                    'total',
                    'current_page',
                    'per_page',
                    'last_page',
                ],
            ],
        ],
    ]);

    $all = collect($response->json())->flatMap(fn ($stage) => $stage['leads']['data']);
    $ids = $all->pluck('id');

    expect($ids)->toContain($o1->id)->and($ids)->toContain($o2->id);
});

test('order kanban filters out won stages when exclude_won_lost is requested', function () {
    Order::factory()->create(['pipeline_stage_id' => $this->stageWon->id]);

    $response = getOrderKanban($this->pipeline->id, ['exclude_won_lost' => true]);
    $response->assertOk();

    $stageIds = collect($response->json())->pluck('id');
    expect($stageIds)->not->toContain($this->stageWon->id);
});

test('order kanban paginates orders per stage', function () {
    for ($i = 0; $i < 15; $i++) {
        Order::factory()->create(['pipeline_stage_id' => $this->stageNew->id]);
    }

    $response = getOrderKanban($this->pipeline->id, ['limit' => 10]);
    $response->assertOk();

    $json = collect($response->json());
    $newStageBucket = $json->firstWhere('id', $this->stageNew->id);

    expect($newStageBucket)->not->toBeNull();
    expect($newStageBucket['leads']['meta']['total'])->toBe(15);
    expect(count($newStageBucket['leads']['data']))->toBe(10);
});

test('order kanban stage update endpoint updates pipeline stage', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => $this->stageNew->id,
    ]);

    $this->putJson(route('admin.orders.stage.update', $order->id), [
        'lead_pipeline_stage_id' => $this->stageInProgress->id,
    ])->assertOk();

    expect($order->refresh()->pipeline_stage_id)->toBe($this->stageInProgress->id);
});

test('order kanban won stage requires and persists closed_at and user_id', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => $this->stageNew->id,
    ]);

    $this->putJson(route('admin.orders.stage.update', $order->id), [
        'lead_pipeline_stage_id' => $this->stageWon->id,
        'user_id'                => $this->user->id,
        'closed_at'              => '16-03-2026',
    ])->assertOk();

    $order->refresh();
    expect($order->pipeline_stage_id)->toBe($this->stageWon->id)
        ->and($order->user_id)->toBe($this->user->id)
        ->and($order->closed_at?->format('Y-m-d'))->toBe('2026-03-16')
        ->and($order->lost_reason)->toBeNull();
});

test('order kanban won stage returns 422 when user_id is missing', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => $this->stageNew->id,
    ]);

    $this->putJson(route('admin.orders.stage.update', $order->id), [
        'lead_pipeline_stage_id' => $this->stageWon->id,
        'closed_at'              => '16-03-2026',
    ])->assertStatus(422);
});

test('order kanban lost stage requires and persists lost_reason', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => $this->stageNew->id,
    ]);

    $this->putJson(route('admin.orders.stage.update', $order->id), [
        'lead_pipeline_stage_id' => $this->stageLost->id,
        'lost_reason'            => LostReason::Price->value,
        'closed_at'              => '16-03-2026',
    ])->assertOk();

    $order->refresh();
    expect($order->pipeline_stage_id)->toBe($this->stageLost->id)
        ->and($order->lost_reason)->toBe(LostReason::Price)
        ->and($order->closed_at?->format('Y-m-d'))->toBe('2026-03-16');
});

test('order kanban lost stage returns 422 when lost_reason is missing', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => $this->stageNew->id,
    ]);

    $this->putJson(route('admin.orders.stage.update', $order->id), [
        'lead_pipeline_stage_id' => $this->stageLost->id,
        'closed_at'              => '16-03-2026',
    ])->assertStatus(422);
});
