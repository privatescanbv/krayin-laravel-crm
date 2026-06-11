<?php

use App\Enums\ActivityType;
use App\Enums\PipelineStage;
use App\Models\Order;
use App\Models\SalesLead;
use Carbon\Carbon;
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

    // workflow activity + "Status gewijzigd" system log from OrderObserver::logFieldChanges
    expect(Activity::where('order_id', $order->id)->count())->toBe(3);
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

test('order workflow deadline_days_from_examination uses first examination date as base', function () {
    $salesLead = SalesLead::factory()->create();
    $examinationDate = now()->addDays(10)->startOfDay();

    Workflow::create([
        'name'           => 'Test examination deadline',
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
                    'title'                          => 'Examination deadline activity',
                    'type'                           => ActivityType::TASK->value,
                    'deadline_days_from_examination' => -2,
                ],
            ],
        ],
    ]);

    $order = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id'    => PipelineStage::ORDER_VERLOREN->id(),
        'first_examination_at' => $examinationDate->toDateString(),
    ]);

    $order->update(['pipeline_stage_id' => PipelineStage::ORDER_RAPPORTEN_ONTVANGEN->id()]);

    $activity = Activity::where('order_id', $order->id)
        ->where('title', 'Examination deadline activity')
        ->first();

    expect($activity)->not->toBeNull();

    $expectedDate = $examinationDate->copy()->subDays(2)->toDateString();
    expect(Carbon::parse($activity->schedule_to)->toDateString())->toBe($expectedDate);
});

test('order workflow deadline_days_from_examination falls back to now when examination date unknown', function () {
    $salesLead = SalesLead::factory()->create();

    Workflow::create([
        'name'           => 'Test examination deadline fallback',
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
                    'title'                          => 'Fallback deadline activity',
                    'type'                           => ActivityType::TASK->value,
                    'deadline_days_from_examination' => 3,
                ],
            ],
        ],
    ]);

    // Order without examination date or resource slots
    $order = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id'    => PipelineStage::ORDER_VERLOREN->id(),
        'first_examination_at' => null,
    ]);

    $order->update(['pipeline_stage_id' => PipelineStage::ORDER_RAPPORTEN_ONTVANGEN->id()]);

    $activity = Activity::where('order_id', $order->id)
        ->where('title', 'Fallback deadline activity')
        ->first();

    expect($activity)->not->toBeNull();

    $expectedDate = now()->addDays(3)->toDateString();
    expect(Carbon::parse($activity->schedule_to)->toDateString())->toBe($expectedDate);
});
