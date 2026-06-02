<?php

use App\Enums\ActivityType;
use App\Enums\PipelineStage;
use App\Models\Order;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Database\Seeders\WorkflowSeeder;
use Webkul\Activity\Models\Activity;
use Webkul\Automation\Models\Workflow;
use Webkul\User\Models\User;

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

test('order workflow create_activity with user_id assigns that user to the created activity', function () {
    $user = User::factory()->create();
    $salesLead = SalesLead::factory()->create();

    Workflow::create([
        'name'           => 'Test order user assignment',
        'description'    => 'Test',
        'entity_type'    => 'orders',
        'event'          => 'order.update_stage.after',
        'condition_type' => 'and',
        'conditions'     => [
            [
                'value'          => (string) PipelineStage::ORDER_RAPPORTEN_ONTVANGEN->id(),
                'operator'       => '==',
                'attribute'      => 'pipeline_stage_id',
                'attribute_type' => 'select',
            ],
        ],
        'actions' => [
            [
                'id'         => 'create_activity',
                'attributes' => [
                    'title'            => 'Assigned order activity',
                    'type'             => ActivityType::TASK->value,
                    'deadline_in_days' => 1,
                    'user_id'          => $user->id,
                ],
            ],
        ],
    ]);

    $order = Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_VERLOREN->id(),
    ]);

    $order->update(['pipeline_stage_id' => PipelineStage::ORDER_RAPPORTEN_ONTVANGEN->id()]);

    $activity = Activity::where('order_id', $order->id)
        ->where('type', '!=', ActivityType::SYSTEM->value)
        ->where('title', 'Assigned order activity')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->user_id)->toBe($user->id);
});
