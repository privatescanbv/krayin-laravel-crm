<?php

use App\Actions\Activities\CreateActivityForOrderAction;
use App\Enums\PipelineStage;
use App\Models\Department;
use App\Models\Order;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Webkul\Activity\Models\Activity;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Group;

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

test('task is assigned to privatescan group when order is in privatescan pipeline, even if saleslead department is hernia', function () {
    // Scenario: lead was originally from Herniapoli but converted to Privatescan.
    // The SalesLead.department_id may still hold the Hernia ID, but the order's
    // pipeline stage belongs to the Privatescan order pipeline.
    $herniaDepartment = Department::where('name', 'hernia')->firstOrFail();
    $privateScanDepartment = Department::where('name', 'privatescan')->firstOrFail();

    $privateScanGroup = Group::where('department_id', $privateScanDepartment->id)->firstOrFail();

    $lead = Lead::factory()->create(['department_id' => $privateScanDepartment->id]);

    // SalesLead with Hernia department (as if created before conversion)
    $salesLead = SalesLead::factory()->create([
        'lead_id'       => $lead->id,
        'department_id' => $herniaDepartment->id,
    ]);

    // Order in the Privatescan order pipeline (stage 30 = ORDER_CONFIRM)
    $order = Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
    ]);

    $action = app(CreateActivityForOrderAction::class);
    $activity = $action->execute($order, false, [
        'title'         => 'Test taak',
        'type'          => 'task',
        'schedule_from' => now(),
        'schedule_to'   => now()->addWeek(),
    ]);

    expect($activity)->toBeInstanceOf(Activity::class);
    expect($activity->group_id)->toBe($privateScanGroup->id);
});

test('task is assigned to hernia group when order is in hernia pipeline', function () {
    $herniaDepartment = Department::where('name', 'hernia')->firstOrFail();
    $herniaGroup = Group::where('department_id', $herniaDepartment->id)->firstOrFail();

    $lead = Lead::factory()->create(['department_id' => $herniaDepartment->id]);

    $salesLead = SalesLead::factory()->create([
        'lead_id'       => $lead->id,
        'department_id' => $herniaDepartment->id,
    ]);

    // Order in the Hernia order pipeline (stage 39 = ORDER_VOORBEREIDEN_HERNIA)
    $order = Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_VOORBEREIDEN_HERNIA->id(),
    ]);

    $action = app(CreateActivityForOrderAction::class);
    $activity = $action->execute($order, false, [
        'title'         => 'Hernia taak',
        'type'          => 'task',
        'schedule_from' => now(),
        'schedule_to'   => now()->addWeek(),
    ]);

    expect($activity)->toBeInstanceOf(Activity::class);
    expect($activity->group_id)->toBe($herniaGroup->id);
});
