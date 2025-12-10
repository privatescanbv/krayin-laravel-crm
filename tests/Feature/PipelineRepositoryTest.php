<?php

use App\Enums\PipelineType;
use Database\Seeders\TestSeeder;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Repositories\PipelineRepository;

beforeEach(function () {
    $this->pipelineRepository = app(PipelineRepository::class);
    $this->seed(TestSeeder::class);
});

test('getDefaultPipeline returns default workflow pipeline when exists', function () {

    $result = $this->pipelineRepository->getDefaultPipeline(PipelineType::BACKOFFICE);

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Privatescan')
        ->and($result->type)->toBe(PipelineType::BACKOFFICE)
        ->and($result->is_default)->toBe(1);
});

test('getDefaultPipeline returns first workflow pipeline when no default exists', function () {

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

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Could not find pipeline by type '.PipelineType::BACKOFFICE->value);
    $this->pipelineRepository->getDefaultPipeline(PipelineType::BACKOFFICE);

});

test('getDefaultPipeline returns default lead pipeline when exists', function () {

    $result = $this->pipelineRepository->getDefaultPipeline(PipelineType::LEAD);

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Privatescan')
        ->and($result->type)->toBe(PipelineType::LEAD)
        ->and($result->is_default)->toBe(1);
});

test('getDefaultPipeline uses correct enum value for database query', function () {

    // Verify the database stores the enum value, not the enum name
    $this->assertDatabaseHas('lead_pipelines', [
        'name' => 'Privatescan',
        'type' => 'workflow', // enum value, not 'WORKFLOW' (enum name)
    ]);

    $result = $this->pipelineRepository->getDefaultPipeline(PipelineType::BACKOFFICE);

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Privatescan')
        ->and($result->type)->toBe(PipelineType::BACKOFFICE);
});

test('getDefaultPipeline works with multiple workflow pipelines', function () {
    // Create an additional workflow pipeline
    Pipeline::factory()->create([
        'name'       => 'Additional Workflow Pipeline',
        'type'       => PipelineType::BACKOFFICE,
        'is_default' => 0,
    ]);

    $result = $this->pipelineRepository->getDefaultPipeline(PipelineType::BACKOFFICE);

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Privatescan') // Should still return the default one
        ->and($result->type)->toBe(PipelineType::BACKOFFICE)
        ->and($result->is_default)->toBe(1);
});
