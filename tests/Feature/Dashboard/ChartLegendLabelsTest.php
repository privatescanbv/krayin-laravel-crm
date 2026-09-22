<?php

use Database\Seeders\TestSeeder;
use Webkul\Admin\Helpers\Reporting\Lead as LeadReporting;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Source;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Models\Type;

beforeEach(function (): void {
    $this->seed(TestSeeder::class);
});

it('uses source type and stage names as chart labels instead of the lead name accessor', function (): void {
    $source = Source::query()->create(['name' => 'Google Ads']);
    $type = Type::query()->create(['name' => 'MRI Abdomen']);
    $openStage = Stage::query()->where('code', 'not like', '%won%')->where('code', 'not like', '%lost%')->firstOrFail();
    $wonStage = Stage::query()->where('code', 'like', '%won%')->firstOrFail();

    Lead::factory()->create([
        'lead_source_id'         => $source->id,
        'lead_type_id'           => $type->id,
        'lead_pipeline_id'       => $wonStage->lead_pipeline_id,
        'lead_pipeline_stage_id' => $wonStage->id,
        'first_name'             => 'Piet',
        'last_name'              => 'Puk',
        'created_at'             => now(),
    ]);

    Lead::factory()->create([
        'lead_source_id'         => $source->id,
        'lead_type_id'           => $type->id,
        'lead_pipeline_id'       => $openStage->lead_pipeline_id,
        'lead_pipeline_stage_id' => $openStage->id,
        'first_name'             => 'Piet',
        'last_name'              => 'Puk',
        'created_at'             => now(),
    ]);

    $reporting = app(LeadReporting::class);

    $sources = $reporting->getTotalWonLeadsBySources();
    $types = $reporting->getTotalWonLeadsByTypes();
    $states = $reporting->getOpenLeadsByStates();

    expect($sources->pluck('name'))->toContain('Google Ads')
        ->and($sources->pluck('name'))->not->toContain('Piet Puk')
        ->and($types->pluck('name'))->toContain('MRI Abdomen')
        ->and($states->pluck('name'))->toContain($openStage->name)
        ->and($states->pluck('name'))->not->toContain('Onbekend');
});
