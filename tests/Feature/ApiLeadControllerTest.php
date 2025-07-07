<?php

use App\Enums\PipelineType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Lead\Repositories\PipelineRepository;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->pipelineRepository = app(PipelineRepository::class);
});

test('getDefaultPipelineByType returns default workflow pipeline when exists', function () {

    $this->assertEquals(3, $this->pipelineRepository->getLeadPipelines()->count());
    $result = $this->pipelineRepository->getDefaultPipelineByType(PipelineType::WORKFLOW);

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Privatescan')
        ->and($result->type)->toBe(PipelineType::WORKFLOW)
        ->and($result->is_default)->toBe(1);
});
