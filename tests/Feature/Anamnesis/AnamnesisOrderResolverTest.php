<?php

use App\Enums\Departments;
use App\Enums\FormType;
use App\Models\Anamnesis;
use App\Models\Department;
use App\Models\Order;
use App\Models\SalesLead;
use App\Services\Anamnesis\AnamnesisOrderResolver;
use Database\Seeders\TestSeeder;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;

beforeEach(function (): void {
    $this->seed(TestSeeder::class);
});

test('AnamnesisOrderResolver finds active order by sales_id', function (): void {
    $salesLead = SalesLead::factory()->create();
    $stage = Stage::factory()->create(['is_won' => false, 'is_lost' => false]);
    $order = Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => $stage->id,
    ]);

    $anamnesis = Anamnesis::factory()->create([
        'sales_id' => $salesLead->id,
        'lead_id'  => null,
    ]);

    expect(app(AnamnesisOrderResolver::class)->findActiveOrderForAnamnesis($anamnesis)?->id)
        ->toBe($order->id);
});

test('AnamnesisOrderResolver resolveFormDepartment uses order pipeline department over lead department', function (): void {
    $herniaDept = Department::firstOrCreate(['name' => Departments::HERNIA->value]);
    $privatescanDept = Department::firstOrCreate(['name' => Departments::PRIVATESCAN->value]);

    $lead = Lead::factory()->create(['department_id' => $herniaDept->id]);
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $stage = Stage::factory()->create([
        'is_won'           => false,
        'is_lost'          => false,
        'lead_pipeline_id' => \App\Enums\PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ORDERS_ID->value,
    ]);
    Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => $stage->id,
    ]);

    $anamnesis = Anamnesis::factory()->create([
        'lead_id'  => $lead->id,
        'sales_id' => $salesLead->id,
    ]);
    $anamnesis->setRelation('lead', $lead->fresh('department'));

    $department = app(AnamnesisOrderResolver::class)->resolveFormDepartment($anamnesis);

    expect($department?->id)->toBe($privatescanDept->id);
});

test('AnamnesisOrderResolver resolveFormDepartment uses sales department when no active order', function (): void {
    $herniaDept = Department::firstOrCreate(['name' => Departments::HERNIA->value]);
    $privatescanDept = Department::firstOrCreate(['name' => Departments::PRIVATESCAN->value]);

    $lead = Lead::factory()->create(['department_id' => $herniaDept->id]);
    $salesLead = SalesLead::factory()->create([
        'lead_id'       => $lead->id,
        'department_id' => $privatescanDept->id,
    ]);

    $anamnesis = Anamnesis::factory()->create([
        'lead_id'  => $lead->id,
        'sales_id' => $salesLead->id,
    ]);
    $anamnesis->setRelation('lead', $lead->fresh('department'));
    $anamnesis->setRelation('sales', $salesLead->fresh('department'));

    $department = app(AnamnesisOrderResolver::class)->resolveFormDepartment($anamnesis);

    expect($department?->id)->toBe($privatescanDept->id);
});

test('AnamnesisOrderResolver resolveFormDepartment falls back to lead department without active order', function (): void {
    $herniaDept = Department::firstOrCreate(['name' => Departments::HERNIA->value]);
    $lead = Lead::factory()->create(['department_id' => $herniaDept->id]);

    $anamnesis = Anamnesis::factory()->create([
        'lead_id'  => $lead->id,
        'sales_id' => null,
    ]);
    $anamnesis->setRelation('lead', $lead->fresh('department'));

    $department = app(AnamnesisOrderResolver::class)->resolveFormDepartment($anamnesis);

    expect($department?->id)->toBe($herniaDept->id);
});

test('mapFormTypeFromDepartment maps hernia department to narcose form type', function (): void {
    $controller = app(\App\Http\Controllers\Admin\AnamnesisController::class);
    $herniaDept = Department::firstOrCreate(['name' => Departments::HERNIA->value]);

    expect($controller->mapFormTypeFromDepartment($herniaDept))->toBe(FormType::HerniaNarcoseForm->value);
});
