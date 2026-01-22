<?php

use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

beforeEach(function () {
    // Create required test data
    test()->user = User::factory()->active()->create();

    test()->pipeline = Pipeline::firstOrCreate([
        'name'        => 'Test Pipeline',
        'is_default'  => 1,
        'rotten_days' => 30,
    ]);

    test()->stage = Stage::firstOrCreate([
        'name'             => 'New',
        'code'             => 'new',
        'lead_pipeline_id' => test()->pipeline->id,
        'sort_order'       => 1,
    ]);
});

test('composite primary key prevents duplicate lead-person combinations', function () {
    // Create test data
    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => test()->pipeline->id,
        'lead_pipeline_stage_id' => test()->stage->id,
        'user_id'                => test()->user->id,
    ]);

    $person = Person::factory()->create(['user_id' => test()->user->id]);

    // Attach person to lead
    $lead->attachPersons([$person->id]);

    // Try to attach same person again - should not create duplicate
    $lead->attachPersons([$person->id]);

    // Should still only have one relationship
    expect($lead->persons->count())->toBe(1)
        ->and(DB::table('lead_persons')->where('lead_id', $lead->id)->where('person_id', $person->id)->count())->toBe(1);
});
