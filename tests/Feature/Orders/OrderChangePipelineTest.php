<?php

use App\Enums\OrderItemStatus;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\SalesLead;
use App\Services\OrderStatusService;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Lead\Models\Stage;
use Webkul\Product\Models\Product;

uses(RefreshDatabase::class);

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);

    $this->seed(TestSeeder::class);

    $user = makeUser();
    $this->actingAs($user, 'user');
});

// ---------------------------------------------------------------------------
// Helper: stage ID via enum
// ---------------------------------------------------------------------------

function psStageId(PipelineStage $stage): int
{
    return $stage->id();
}

function herniaStageId(PipelineStage $stage): int
{
    return $stage->id();
}

// ---------------------------------------------------------------------------
// Saving a Hernia stage via the order-update endpoint persists the correct ID
// ---------------------------------------------------------------------------

test('saving order with hernia stage id moves order to hernia pipeline', function () {
    $salesLead = SalesLead::factory()->create();

    $order = Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
    ]);

    $response = $this->putJson(
        route('admin.orders.update', ['id' => $order->id]),
        [
            'title'             => $order->title,
            'sales_lead_id'     => $salesLead->id,
            'pipeline_stage_id' => PipelineStage::ORDER_VOORBEREIDEN_HERNIA->id(),
        ]
    );

    $response->assertSuccessful();

    $order->refresh();
    expect($order->pipeline_stage_id)->toBe(PipelineStage::ORDER_VOORBEREIDEN_HERNIA->id());
    expect($order->stage->lead_pipeline_id)->toBe(PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value);
});

test('saving order with privatescan stage id moves order to privatescan pipeline', function () {
    $salesLead = SalesLead::factory()->create();

    $order = Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_VOORBEREIDEN_HERNIA->id(),
    ]);

    $response = $this->putJson(
        route('admin.orders.update', ['id' => $order->id]),
        [
            'title'             => $order->title,
            'sales_lead_id'     => $salesLead->id,
            'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
        ]
    );

    $response->assertSuccessful();

    $order->refresh();
    expect($order->pipeline_stage_id)->toBe(PipelineStage::ORDER_CONFIRM->id());
    expect($order->stage->lead_pipeline_id)->toBe(PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ORDERS_ID->value);
});

// ---------------------------------------------------------------------------
// recalculateAndPersist respects the order's pipeline, not the salesLead department
// ---------------------------------------------------------------------------

test('recalculate does not reset hernia stage to privatescan when order has plannable NEW items', function () {
    $salesLead = SalesLead::factory()->create(); // Privatescan salesLead (no department_id)

    $order = Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_VOORBEREIDEN_HERNIA->id(), // Hernia pipeline
    ]);

    // Add a plannable item (has partner product) with status NEW
    $product = Product::factory()->create();
    PartnerProduct::factory()->create(['product_id' => $product->id]);
    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'status'     => OrderItemStatus::NEW->value,
    ]);

    $service = app(OrderStatusService::class);
    $service->recalculateAndPersist($order);

    $order->refresh();
    // Must stay in Hernia pipeline — not be reset to ORDER_CONFIRM (Privatescan)
    expect($order->pipeline_stage_id)->toBe(PipelineStage::ORDER_VOORBEREIDEN_HERNIA->id());
    expect($order->stage->lead_pipeline_id)->toBe(PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value);
});

test('recalculate keeps privatescan stage when privatescan order has plannable NEW items', function () {
    $salesLead = SalesLead::factory()->create();

    $order = Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id(), // Privatescan ingepland
    ]);

    $product = Product::factory()->create();
    PartnerProduct::factory()->create(['product_id' => $product->id]);
    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'status'     => OrderItemStatus::NEW->value,
    ]);

    $service = app(OrderStatusService::class);
    $service->recalculateAndPersist($order);

    $order->refresh();
    // Plannable item is NEW → reset to first Privatescan stage
    expect($order->pipeline_stage_id)->toBe(PipelineStage::ORDER_CONFIRM->id());
    expect($order->stage->lead_pipeline_id)->toBe(PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ORDERS_ID->value);
});

// ---------------------------------------------------------------------------
// Positional mapping: same index in each pipeline maps to equivalent stage
// ---------------------------------------------------------------------------

test('privatescan and hernia order pipelines have the same number of stages', function () {
    $psCount = Stage::where('lead_pipeline_id', PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ORDERS_ID->value)->count();
    $herniaCount = Stage::where('lead_pipeline_id', PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value)->count();

    expect($psCount)->toBe($herniaCount);
});

test('each privatescan order stage maps to an equivalent hernia stage by index', function () {
    $psStages = Stage::where('lead_pipeline_id', PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ORDERS_ID->value)
        ->orderBy('sort_order')
        ->get();

    $herniaStages = Stage::where('lead_pipeline_id', PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value)
        ->orderBy('sort_order')
        ->get();

    expect($psStages)->toHaveCount($herniaStages->count());

    foreach ($psStages as $i => $psStage) {
        $herniaStage = $herniaStages[$i];
        // Both stages at the same position should share the same won/lost status
        expect($psStage->is_won)->toBe($herniaStage->is_won, "Position {$i}: is_won mismatch");
        expect($psStage->is_lost)->toBe($herniaStage->is_lost, "Position {$i}: is_lost mismatch");
    }
});
