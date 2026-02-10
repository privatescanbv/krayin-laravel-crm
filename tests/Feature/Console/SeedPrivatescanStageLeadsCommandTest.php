<?php

use App\Enums\Departments;
use App\Enums\PipelineDefaultKeys;
use App\Models\Department;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Artisan;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

test('leads:seed-privatescan-stages creates N leads per privatescan stage', function () {
    $perStage = 15;

    $user = User::factory()->create();

    $exitCode = Artisan::call('leads:seed-privatescan-stages', [
        '--per-stage' => $perStage,
        '--user-id'   => (string) $user->id,
    ]);

    expect($exitCode)->toBe(0);

    $departmentId = Department::query()
        ->where('name', Departments::PRIVATESCAN->value)
        ->firstOrFail()
        ->id;

    $pipelineId = PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value;

    $stages = Stage::query()
        ->where('lead_pipeline_id', $pipelineId)
        ->orderBy('sort_order')
        ->get();

    expect($stages)->not->toBeEmpty();

    foreach ($stages as $stage) {
        $count = Lead::query()
            ->where('lead_pipeline_id', $pipelineId)
            ->where('lead_pipeline_stage_id', $stage->id)
            ->where('department_id', $departmentId)
            ->where('user_id', $user->id)
            ->count();

        expect($count)->toBe($perStage);
    }
});
