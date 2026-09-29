<?php

namespace Tests\Feature\Activities;

use Database\Seeders\TestSeeder;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Source;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Models\Type;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    $role = Role::first() ?? Role::create([
        'name'            => 'Admin',
        'description'     => 'Administrator role',
        'permission_type' => 'all',
        'permissions'     => [],
    ]);

    $this->user = User::create([
        'name'     => 'Test User',
        'email'    => 'activity-label-test@example.com',
        'password' => bcrypt('password'),
        'status'   => 1,
        'role_id'  => $role->id,
    ]);

    $this->pipeline = Pipeline::first() ?? Pipeline::create([
        'name'        => 'Test Pipeline',
        'is_default'  => 1,
        'rotten_days' => 30,
    ]);

    $this->stageOne = Stage::create([
        'name'             => 'Stage One',
        'code'             => 'stage-one',
        'lead_pipeline_id' => $this->pipeline->id,
        'sort_order'       => 1,
        'probability'      => 10,
    ]);

    $this->stageTwo = Stage::create([
        'name'             => 'Stage Two',
        'code'             => 'stage-two',
        'lead_pipeline_id' => $this->pipeline->id,
        'sort_order'       => 2,
        'probability'      => 20,
    ]);

    $this->sourceOne = Source::first() ?? Source::create(['name' => 'Source One']);
    $this->sourceTwo = Source::create(['name' => 'Source Two']);

    $this->type = Type::first() ?? Type::create(['name' => 'Test Type']);
});

test('activity log shows pipeline stage names instead of raw ids', function () {
    $this->actingAs($this->user);

    $lead = Lead::create([
        'title'                  => 'Test Lead',
        'description'            => 'Test Description',
        'first_name'             => 'John',
        'last_name'              => 'Doe',
        'emails'                 => ['john@example.com'],
        'phones'                 => ['1234567890'],
        'user_id'                => $this->user->id,
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stageOne->id,
        'lead_source_id'         => $this->sourceOne->id,
        'lead_type_id'           => $this->type->id,
        'created_by'             => $this->user->id,
        'updated_by'             => $this->user->id,
    ]);

    $lead->update([
        'lead_pipeline_stage_id' => $this->stageTwo->id,
        'lead_source_id'         => $this->sourceTwo->id,
    ]);

    $activities = $lead->activities()->get();

    $stageActivity = $activities->first(fn ($activity) => $activity->additional['attribute'] ?? null === 'Pipeline fase'
        || ($activity->additional['new']['value'] ?? null) === $this->stageTwo->id);

    expect($stageActivity)->not->toBeNull()
        ->and($stageActivity->additional['old']['label'])->toBe($this->stageOne->name)
        ->and($stageActivity->additional['new']['label'])->toBe($this->stageTwo->name)
        ->and($stageActivity->additional['old']['label'])->not->toBe((string) $this->stageOne->id)
        ->and($stageActivity->additional['new']['label'])->not->toBe((string) $this->stageTwo->id);

    $sourceActivity = $activities->first(fn ($activity) => ($activity->additional['new']['value'] ?? null) === $this->sourceTwo->id);

    expect($sourceActivity)->not->toBeNull()
        ->and($sourceActivity->additional['old']['label'])->toBe($this->sourceOne->name)
        ->and($sourceActivity->additional['new']['label'])->toBe($this->sourceTwo->name);
});
