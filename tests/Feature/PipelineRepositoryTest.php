<?php

use App\Enums\PipelineType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Repositories\PipelineRepository;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->pipelineRepository = app(PipelineRepository::class);

    // Debug: check what's in the database before we start
    $initialCount = Pipeline::count();
    echo "Initial pipeline count: $initialCount\n";

    // Clear existing data first to ensure clean state
    Pipeline::query()->delete();

    // Create the exact seeder data for this test
    Pipeline::create([
        'id'         => 1,
        'name'       => 'Privatescan',
        'is_default' => 1,
        'type'       => PipelineType::LEAD,
    ]);

    Pipeline::create([
        'id'         => 2,
        'name'       => 'Hernia',
        'is_default' => 0,
        'type'       => PipelineType::LEAD,
    ]);

    Pipeline::create([
        'id'         => 3,
        'name'       => 'Privatescan',
        'is_default' => 1,
        'type'       => PipelineType::WORKFLOW,
    ]);

    Pipeline::create([
        'id'         => 4,
        'name'       => 'Hernia',
        'is_default' => 0,
        'type'       => PipelineType::WORKFLOW,
    ]);
});

test('getDefaultPipelineByType returns default workflow pipeline when exists', function () {

    $this->assertEquals(2, $this->pipelineRepository->leadPipelines()->count());
    $result = $this->pipelineRepository->getDefaultPipelineByType(PipelineType::WORKFLOW);

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Privatescan')
        ->and($result->type)->toBe(PipelineType::WORKFLOW)
        ->and($result->is_default)->toBe(1);
});

test('getDefaultPipelineByType returns first workflow pipeline when no default exists', function () {

    // Remove the default flag from the existing workflow pipeline
    Pipeline::where('type', PipelineType::WORKFLOW)
        ->where('is_default', 1)
        ->update(['is_default' => 0]);

    // Create additional non-default workflow pipelines
    Pipeline::factory()->create([
        'name'       => 'First Workflow Pipeline',
        'type'       => PipelineType::WORKFLOW,
        'is_default' => 0,
    ]);

    $result = $this->pipelineRepository->getDefaultPipelineByType(PipelineType::WORKFLOW);

    expect($result)->not->toBeNull()
        ->and($result->type)->toBe(PipelineType::WORKFLOW);

    // Should return the first workflow pipeline (either existing or newly created)
    $this->assertContains($result->name, ['Privatescan', 'Hernia', 'First Workflow Pipeline']);
});

test('getDefaultPipelineByType returns default lead pipeline when exists', function () {

    $result = $this->pipelineRepository->getDefaultPipelineByType(PipelineType::LEAD);

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Privatescan')
        ->and($result->type)->toBe(PipelineType::LEAD)
        ->and($result->is_default)->toBe(1);
});

test('getDefaultPipelineByType returns null when no pipeline exists for type', function () {

    // Remove all workflow pipelines
    Pipeline::where('type', PipelineType::WORKFLOW)->delete();

    $result = $this->pipelineRepository->getDefaultPipelineByType(PipelineType::WORKFLOW);

    expect($result)->toBeNull();
});

test('getDefaultPipelineByType uses correct enum value for database query', function () {

    // Verify the database stores the enum value, not the enum name
    $this->assertDatabaseHas('lead_pipelines', [
        'name' => 'Privatescan',
        'type' => 'workflow', // enum value, not 'WORKFLOW' (enum name)
    ]);

    $result = $this->pipelineRepository->getDefaultPipelineByType(PipelineType::WORKFLOW);

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Privatescan')
        ->and($result->type)->toBe(PipelineType::WORKFLOW);
});

test('getDefaultPipelineByType works with multiple workflow pipelines', function () {
    // Create an additional workflow pipeline
    Pipeline::factory()->create([
        'name'       => 'Additional Workflow Pipeline',
        'type'       => PipelineType::WORKFLOW,
        'is_default' => 0,
    ]);

    $result = $this->pipelineRepository->getDefaultPipelineByType(PipelineType::WORKFLOW);

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Privatescan') // Should still return the default one
        ->and($result->type)->toBe(PipelineType::WORKFLOW)
        ->and($result->is_default)->toBe(1);
});

test('getDefaultPipelineByType returns non-default workflow pipeline when no default exists', function () {

    // Remove default flag from all workflow pipelines
    Pipeline::where('type', PipelineType::WORKFLOW)->update(['is_default' => 0]);

    $result = $this->pipelineRepository->getDefaultPipelineByType(PipelineType::WORKFLOW);

    expect($result)->not->toBeNull()
        ->and($result->type)->toBe(PipelineType::WORKFLOW)
        ->and($result->is_default)->toBe(0);

    // Should return one of the existing workflow pipelines (Privatescan or Hernia)
    $this->assertContains($result->name, ['Privatescan', 'Hernia']);
});
