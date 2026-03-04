<?php

use App\Enums\PipelineStage;
use App\Models\Order;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Database\Seeders\WorkflowSeeder;
use Webkul\Activity\Models\Activity;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->seed(WorkflowSeeder::class);
});

test('creating an order at a stage with a workflow automatically creates an activity on the order', function () {
    $salesLead = SalesLead::factory()->create();
    $stage = PipelineStage::ORDER_CONFIRM; // id=30, entity='order', status=null

    $order = Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => $stage->id(),
    ]);

    expect(Activity::where('order_id', $order->id)->count())->toBe(1);
});

test('updating an order stage to one with a workflow automatically creates an activity on the order', function () {
    $salesLead = SalesLead::factory()->create();

    // Create at a lost stage — WorkflowSeeder excludes lost/won stages, so no activity is created on creation
    $order = Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_VERLOREN->id(), // id=38, is_lost, no workflow
    ]);

    expect(Activity::where('order_id', $order->id)->count())->toBe(0);

    $order->update(['pipeline_stage_id' => PipelineStage::ORDER_RAPPORTEN_ONTVANGEN->id()]);

    expect(Activity::where('order_id', $order->id)->count())->toBe(2);
});
