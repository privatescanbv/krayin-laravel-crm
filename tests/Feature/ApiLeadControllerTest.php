<?php

use App\Enums\PipelineType;
use Database\Seeders\TestSeeder;
use Webkul\Lead\Repositories\PipelineRepository;

beforeEach(function () {
    $this->pipelineRepository = app(PipelineRepository::class);
    $this->seed(TestSeeder::class);
});

test('getDefaultPipelineByType returns default workflow pipeline when exists', function () {

    $this->assertEquals(3, $this->pipelineRepository->getLeadPipelines()->count());
    $result = $this->pipelineRepository->getDefaultPipelineByType(PipelineType::BACKOFFICE);

    expect($result)->not->toBeNull()
        ->and($result->name)->toBe('Privatescan')
        ->and($result->type)->toBe(PipelineType::BACKOFFICE)
        ->and($result->is_default)->toBe(1);
});
