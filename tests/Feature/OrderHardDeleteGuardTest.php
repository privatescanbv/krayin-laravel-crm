<?php

use App\Enums\Departments;
use App\Enums\PipelineStage;
use App\Http\Controllers\Admin\SalesLeadController;
use App\Models\Department;
use App\Models\Order;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Webkul\Lead\Models\Lead;

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

test('privatescan order delete moves order to lost instead of hard deleting', function () {
    $department = Department::where('name', Departments::PRIVATESCAN->value)->firstOrFail();
    $lead = Lead::factory()->create(['department_id' => $department->id]);
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
        'closed_at'         => null,
    ]);

    $order->delete();

    $order->refresh();

    expect(Order::find($order->id))->not->toBeNull()
        ->and($order->pipeline_stage_id)->toBe(38)
        ->and($order->closed_at)->not->toBeNull();
});

test('hernia order delete moves order to hernia lost instead of hard deleting', function () {
    $department = Department::where('name', Departments::HERNIA->value)->firstOrFail();
    $lead = Lead::factory()->create(['department_id' => $department->id]);
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_VOORBEREIDEN_HERNIA->id(),
        'closed_at'         => null,
    ]);

    $order->delete();

    $order->refresh();

    expect(Order::find($order->id))->not->toBeNull()
        ->and($order->pipeline_stage_id)->toBe(47)
        ->and($order->closed_at)->not->toBeNull();
});

test('deleting sales lead marks it as lost and leaves linked orders as lost', function () {
    $department = Department::where('name', Departments::PRIVATESCAN->value)->firstOrFail();
    $lead = Lead::factory()->create(['department_id' => $department->id]);
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
        'closed_at'         => null,
    ]);

    app(SalesLeadController::class)->delete($salesLead->id);

    $order->refresh();
    $salesLead->refresh();

    // SalesLead is no longer hard deleted — it is marked as lost
    expect(SalesLead::find($salesLead->id))->not->toBeNull()
        ->and($salesLead->closed_at)->not->toBeNull()
        // Linked order is moved to lost stage and sales_lead_id remains intact
        ->and(Order::find($order->id))->not->toBeNull()
        ->and($order->pipeline_stage_id)->toBe(38)
        ->and($order->closed_at)->not->toBeNull()
        ->and($order->sales_lead_id)->toBe($salesLead->id);
});
