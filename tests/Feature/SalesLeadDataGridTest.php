<?php

use App\Enums\PipelineType;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Person;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);

    $this->seed(TestSeeder::class);

    $user = makeUser();
    $this->actingAs($user, 'user');

    // Ensure we have a BACKOFFICE pipeline with active, won and lost stages
    $pipeline = Pipeline::where('type', PipelineType::BACKOFFICE)->first() ?? Pipeline::create([
        'name'        => 'Default Workflow Pipeline',
        'type'        => PipelineType::BACKOFFICE,
        'is_default'  => 1,
        'rotten_days' => 30,
    ]);

    $activeStage = Stage::where('lead_pipeline_id', $pipeline->id)
        ->where('is_won', 0)->where('is_lost', 0)->first();
    if (! $activeStage) {
        $activeStage = Stage::create([
            'name'             => 'Active',
            'code'             => 'active',
            'lead_pipeline_id' => $pipeline->id,
            'sort_order'       => 1,
        ]);
    }

    $wonStage = Stage::where('lead_pipeline_id', $pipeline->id)->where('is_won', 1)->first();
    if (! $wonStage) {
        $wonStage = Stage::create([
            'name'             => 'Won',
            'code'             => 'won',
            'lead_pipeline_id' => $pipeline->id,
            'sort_order'       => 10,
            'is_won'           => 1,
        ]);
    }

    $lostStage = Stage::where('lead_pipeline_id', $pipeline->id)->where('is_lost', 1)->first();
    if (! $lostStage) {
        $lostStage = Stage::create([
            'name'             => 'Lost',
            'code'             => 'lost',
            'lead_pipeline_id' => $pipeline->id,
            'sort_order'       => 11,
            'is_lost'          => 1,
        ]);
    }

    test()->pipeline = $pipeline;
    test()->activeStage = $activeStage;
    test()->wonStage = $wonStage;
    test()->lostStage = $lostStage;
});

test('datagrid returns sales leads filtered by lead_id', function () {
    $lead1 = Lead::factory()->create();
    $lead2 = Lead::factory()->create();

    $salesA = SalesLead::create([
        'name'              => 'Sales for Lead 1',
        'pipeline_stage_id' => test()->activeStage->id,
        'lead_id'           => $lead1->id,
    ]);

    $salesB = SalesLead::create([
        'name'              => 'Sales for Lead 2',
        'pipeline_stage_id' => test()->activeStage->id,
        'lead_id'           => $lead2->id,
    ]);

    $response = $this->getJson(route('admin.sales-leads.get', [
        'view_type' => 'table',
        'lead_id'   => $lead1->id,
    ]));

    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($salesA->id);
    expect($ids)->not->toContain($salesB->id);
});

test('datagrid filters active sales leads (status_bucket=active)', function () {
    $lead = Lead::factory()->create();

    $activeSales = SalesLead::create([
        'name'              => 'Active Sales',
        'pipeline_stage_id' => test()->activeStage->id,
        'lead_id'           => $lead->id,
    ]);

    $wonSales = SalesLead::create([
        'name'              => 'Won Sales',
        'pipeline_stage_id' => test()->wonStage->id,
        'lead_id'           => $lead->id,
    ]);

    $lostSales = SalesLead::create([
        'name'              => 'Lost Sales',
        'pipeline_stage_id' => test()->lostStage->id,
        'lead_id'           => $lead->id,
    ]);

    $response = $this->getJson(route('admin.sales-leads.get', [
        'view_type'     => 'table',
        'lead_id'       => $lead->id,
        'status_bucket' => 'active',
    ]));

    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($activeSales->id);
    expect($ids)->not->toContain($wonSales->id);
    expect($ids)->not->toContain($lostSales->id);
});

test('datagrid filters closed sales leads (status_bucket=closed)', function () {
    $lead = Lead::factory()->create();

    $activeSales = SalesLead::create([
        'name'              => 'Active Sales',
        'pipeline_stage_id' => test()->activeStage->id,
        'lead_id'           => $lead->id,
    ]);

    $wonSales = SalesLead::create([
        'name'              => 'Won Sales',
        'pipeline_stage_id' => test()->wonStage->id,
        'lead_id'           => $lead->id,
    ]);

    $lostSales = SalesLead::create([
        'name'              => 'Lost Sales',
        'pipeline_stage_id' => test()->lostStage->id,
        'lead_id'           => $lead->id,
    ]);

    $response = $this->getJson(route('admin.sales-leads.get', [
        'view_type'     => 'table',
        'lead_id'       => $lead->id,
        'status_bucket' => 'closed',
    ]));

    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->not->toContain($activeSales->id);
    expect($ids)->toContain($wonSales->id);
    expect($ids)->toContain($lostSales->id);
});

test('datagrid without status_bucket returns all sales leads for a lead', function () {
    $lead = Lead::factory()->create();

    $activeSales = SalesLead::create([
        'name'              => 'Active Sales',
        'pipeline_stage_id' => test()->activeStage->id,
        'lead_id'           => $lead->id,
    ]);

    $wonSales = SalesLead::create([
        'name'              => 'Won Sales',
        'pipeline_stage_id' => test()->wonStage->id,
        'lead_id'           => $lead->id,
    ]);

    $response = $this->getJson(route('admin.sales-leads.get', [
        'view_type' => 'table',
        'lead_id'   => $lead->id,
    ]));

    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($activeSales->id);
    expect($ids)->toContain($wonSales->id);
});

test('datagrid filters sales leads by person_id via pivot table', function () {
    $lead = Lead::factory()->create();
    $person1 = Person::factory()->create();
    $person2 = Person::factory()->create();

    $salesA = SalesLead::create([
        'name'              => 'Sales for Person 1',
        'pipeline_stage_id' => test()->activeStage->id,
        'lead_id'           => $lead->id,
    ]);

    $salesB = SalesLead::create([
        'name'              => 'Sales for Person 2',
        'pipeline_stage_id' => test()->activeStage->id,
        'lead_id'           => $lead->id,
    ]);

    // Link persons via pivot table
    DB::table('saleslead_persons')->insert([
        ['saleslead_id' => $salesA->id, 'person_id' => $person1->id],
        ['saleslead_id' => $salesB->id, 'person_id' => $person2->id],
    ]);

    $response = $this->getJson(route('admin.sales-leads.get', [
        'view_type'  => 'table',
        'person_id'  => $person1->id,
    ]));

    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($salesA->id);
    expect($ids)->not->toContain($salesB->id);
});
