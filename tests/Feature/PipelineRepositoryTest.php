<?php

use App\Enums\PipelineType;
use Database\Seeders\TestSeeder;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Repositories\PipelineRepository;

beforeEach(function () {
    $this->pipelineRepository = app(PipelineRepository::class);
    $this->seed(TestSeeder::class);
});

test('getDefaultPipelineByType returns default workflow pipeline when exists', function () {

    //    $this->assertEquals(2, $this->pipelineRepository->leadPipelines()->count());
    $result = $this->pipelineRepository->getDefaultPipelineByType(PipelineType::BACKOFFICE);

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Privatescan')
        ->and($result->type)->toBe(PipelineType::BACKOFFICE)
        ->and($result->is_default)->toBe(1);
});

test('getDefaultPipelineByType returns first workflow pipeline when no default exists', function () {

    // Remove the default flag from the existing workflow pipeline
    Pipeline::where('type', PipelineType::BACKOFFICE)
        ->where('is_default', 1)
        ->update(['is_default' => 0]);

    // Create additional non-default workflow pipelines
    Pipeline::factory()->create([
        'name'       => 'First Workflow Pipeline',
        'type'       => PipelineType::BACKOFFICE,
        'is_default' => 0,
    ]);

    $result = $this->pipelineRepository->getDefaultPipelineByType(PipelineType::BACKOFFICE);

    expect($result)->not->toBeNull()
        ->and($result->type)->toBe(PipelineType::BACKOFFICE);

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
    Pipeline::where('type', PipelineType::BACKOFFICE)->delete();

    $result = $this->pipelineRepository->getDefaultPipelineByType(PipelineType::BACKOFFICE);

    expect($result)->toBeNull();
});

test('getDefaultPipelineByType uses correct enum value for database query', function () {

    // Verify the database stores the enum value, not the enum name
    $this->assertDatabaseHas('lead_pipelines', [
        'name' => 'Privatescan',
        'type' => 'workflow', // enum value, not 'WORKFLOW' (enum name)
    ]);

    $result = $this->pipelineRepository->getDefaultPipelineByType(PipelineType::BACKOFFICE);

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Privatescan')
        ->and($result->type)->toBe(PipelineType::BACKOFFICE);
});

test('getDefaultPipelineByType works with multiple workflow pipelines', function () {
    // Create an additional workflow pipeline
    Pipeline::factory()->create([
        'name'       => 'Additional Workflow Pipeline',
        'type'       => PipelineType::BACKOFFICE,
        'is_default' => 0,
    ]);

    $result = $this->pipelineRepository->getDefaultPipelineByType(PipelineType::BACKOFFICE);

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Privatescan') // Should still return the default one
        ->and($result->type)->toBe(PipelineType::BACKOFFICE)
        ->and($result->is_default)->toBe(1);
});

test('getDefaultPipelineByType returns non-default workflow pipeline when no default exists', function () {

    // Remove default flag from all workflow pipelines
    Pipeline::where('type', PipelineType::BACKOFFICE)->update(['is_default' => 0]);

    $result = $this->pipelineRepository->getDefaultPipelineByType(PipelineType::BACKOFFICE);

    expect($result)->not->toBeNull()
        ->and($result->type)->toBe(PipelineType::BACKOFFICE)
        ->and($result->is_default)->toBe(0);

    // Should return one of the existing workflow pipelines (Privatescan or Hernia)
    $this->assertContains($result->name, ['Privatescan', 'Hernia']);
});
