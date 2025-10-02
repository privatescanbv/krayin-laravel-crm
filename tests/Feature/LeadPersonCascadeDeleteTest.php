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

test('cascade delete removes lead_persons records when lead is deleted', function () {
    // Create a lead
    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => test()->pipeline->id,
        'lead_pipeline_stage_id' => test()->stage->id,
        'user_id'                => test()->user->id,
    ]);

    // Create persons
    $person1 = Person::factory()->create(['user_id' => test()->user->id]);
    $person2 = Person::factory()->create(['user_id' => test()->user->id]);

    // Attach persons to lead
    $lead->attachPersons([$person1->id, $person2->id]);

    // Verify relationships exist
    expect($lead->persons->count())->toBe(2);
    expect(DB::table('lead_persons')->where('lead_id', $lead->id)->count())->toBe(2);

    // Delete the lead
    $lead->delete();

    // Verify cascade delete worked
    expect(DB::table('lead_persons')->where('lead_id', $lead->id)->count())->toBe(0);

    // Verify persons still exist (should not be deleted)
    expect(Person::find($person1->id))->not->toBeNull();
    expect(Person::find($person2->id))->not->toBeNull();
});

test('cascade delete removes lead_persons records when person is deleted', function () {
    // Create leads
    $lead1 = Lead::factory()->create([
        'lead_pipeline_id'       => test()->pipeline->id,
        'lead_pipeline_stage_id' => test()->stage->id,
        'user_id'                => test()->user->id,
    ]);

    $lead2 = Lead::factory()->create([
        'lead_pipeline_id'       => test()->pipeline->id,
        'lead_pipeline_stage_id' => test()->stage->id,
        'user_id'                => test()->user->id,
    ]);

    // Create person
    $person = Person::factory()->create(['user_id' => test()->user->id]);

    // Attach person to multiple leads
    $lead1->attachPersons([$person->id]);
    $lead2->attachPersons([$person->id]);

    // Verify relationships exist
    expect($person->leads->count())->toBe(2);
    expect(DB::table('lead_persons')->where('person_id', $person->id)->count())->toBe(2);

    // Delete the person
    $person->delete();

    // Verify cascade delete worked
    expect(DB::table('lead_persons')->where('person_id', $person->id)->count())->toBe(0);

    // Verify leads still exist (should not be deleted)
    expect(Lead::find($lead1->id))->not->toBeNull();
    expect(Lead::find($lead2->id))->not->toBeNull();
});

test('lead_persons table has correct structure following existing pivot table pattern', function () {
    // Check table structure (MySQL compatible)
    $columns = DB::select('DESCRIBE lead_persons');
    $columnNames = collect($columns)->pluck('Field')->toArray();

    // Should not have id column (following lead_activities pattern)
    expect($columnNames)->not->toContain('id');

    // Should have lead_id and person_id
    expect($columnNames)->toContain('lead_id');
    expect($columnNames)->toContain('person_id');

    // Should not have timestamps (following existing pivot table pattern)
    expect($columnNames)->not->toContain('created_at');
    expect($columnNames)->not->toContain('updated_at');
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
    expect($lead->persons->count())->toBe(1);
    expect(DB::table('lead_persons')->where('lead_id', $lead->id)->where('person_id', $person->id)->count())->toBe(1);
});
