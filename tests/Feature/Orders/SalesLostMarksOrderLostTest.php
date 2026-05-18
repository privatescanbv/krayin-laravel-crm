<?php

namespace Tests\Feature\Settings;

use App\Enums\Departments;
use App\Enums\LostReason;
use App\Enums\PipelineStage;
use App\Models\Department;
use App\Models\Order;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Webkul\Lead\Models\Lead;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    $this->seed(TestSeeder::class);

    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('privatescan sales lost sets linked order to order-verloren and order view still loads', function () {
    $dept = Department::where('name', Departments::PRIVATESCAN->value)->firstOrFail();
    $lead = Lead::factory()->create(['department_id' => $dept->id]);

    $salesLead = SalesLead::factory()->create([
        'lead_id'             => $lead->id,
        'pipeline_stage_id'   => PipelineStage::SALES_IN_BEHANDELING->id(),
        'lost_reason'         => null,
    ]);

    $order = Order::factory()->create([
        'sales_lead_id'      => $salesLead->id,
        'pipeline_stage_id'  => PipelineStage::ORDER_CONFIRM->id(),
    ]);

    $salesLead->update([
        'pipeline_stage_id' => PipelineStage::SALES_NIET_SUCCESVOL_AFGEROND->id(),
        'lost_reason'       => LostReason::Price,
        'closed_at'         => now(),
    ]);

    $order->refresh();

    expect($order->pipeline_stage_id)->toBe(PipelineStage::ORDER_VERLOREN->id())
        ->and($order->lost_reason)->toBe(LostReason::Price);

    $this->get(route('admin.orders.view', $order->id))->assertOk();
});

test('hernia sales lost sets linked order to order-verloren-hernia and order view still loads', function () {
    $dept = Department::where('name', Departments::HERNIA->value)->firstOrFail();
    $lead = Lead::factory()->create(['department_id' => $dept->id]);

    $salesLead = SalesLead::factory()->create([
        'lead_id'             => $lead->id,
        'pipeline_stage_id'   => PipelineStage::SALES_ORDER_PREVENTIE_HERNIA->id(),
        'lost_reason'         => null,
    ]);

    $order = Order::factory()->create([
        'sales_lead_id'      => $salesLead->id,
        'pipeline_stage_id'  => PipelineStage::ORDER_VOORBEREIDEN_HERNIA->id(),
    ]);

    $salesLead->update([
        'pipeline_stage_id' => PipelineStage::SALES_COMPLETE_NOT_SUCCESSFULLY_HERNIA->id(),
        'lost_reason'       => LostReason::NoMRI,
        'closed_at'         => now(),
    ]);

    $order->refresh();

    expect($order->pipeline_stage_id)->toBe(PipelineStage::ORDER_VERLOREN_HERNIA->id())
        ->and($order->lost_reason)->toBe(LostReason::NoMRI);

    $this->get(route('admin.orders.view', $order->id))->assertOk();
});

test('sales lost does not change order that is already won', function () {
    $dept = Department::where('name', Departments::PRIVATESCAN->value)->firstOrFail();
    $lead = Lead::factory()->create(['department_id' => $dept->id]);

    $salesLead = SalesLead::factory()->create([
        'lead_id'             => $lead->id,
        'pipeline_stage_id'   => PipelineStage::SALES_IN_BEHANDELING->id(),
        'lost_reason'         => null,
    ]);

    $order = Order::factory()->create([
        'sales_lead_id'      => $salesLead->id,
        'pipeline_stage_id'  => PipelineStage::ORDER_GEWONNEN->id(),
    ]);

    $salesLead->update([
        'pipeline_stage_id' => PipelineStage::SALES_NIET_SUCCESVOL_AFGEROND->id(),
        'lost_reason'       => LostReason::Price,
        'closed_at'         => now(),
    ]);

    $order->refresh();

    expect($order->pipeline_stage_id)->toBe(PipelineStage::ORDER_GEWONNEN->id());
});
