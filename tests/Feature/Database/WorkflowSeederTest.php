<?php

use App\Enums\PipelineStage;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Support\Facades\DB;

test('WorkflowSeeder creates a workflow for each PipelineStage case except LOST and WON', function () {
    $this->seed(WorkflowSeeder::class);

    $expectedStages = array_values(array_filter(
        PipelineStage::cases(),
        fn (PipelineStage $stage) => ! $stage->isWon() && ! $stage->isLost() && $stage != PipelineStage::NO_PIPELINE
    ));

    $workflows = DB::table('workflows')->get(['id', 'conditions']);

    $seededStageIds = $workflows
        ->map(function (object $workflow): ?int {
            $conditions = json_decode($workflow->conditions ?? '[]', true);

            if (! is_array($conditions) || ! isset($conditions[0]['value'])) {
                return null;
            }

            return (int) $conditions[0]['value'];
        })
        ->filter()
        ->values()
        ->all();

    expect($workflows->count())->toBe(count($expectedStages));

    foreach ($expectedStages as $stage) {
        expect($seededStageIds)->toContain($stage->id());
    }
});
