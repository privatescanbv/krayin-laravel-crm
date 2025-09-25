<?php

use App\Services\LeadStatusTransitionValidator;
use Illuminate\Validation\ValidationException;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

beforeEach(function () {
    // Reset validator defaults and rules between tests
    LeadStatusTransitionValidator::reset();
    // Ensure an authenticated user exists to satisfy activity/user FKs
    test()->user = User::factory()->create();
    test()->actingAs(test()->user);
    // Create a test pipeline
    test()->pipeline = Pipeline::create([
        'name'       => 'Test Pipeline',
        'is_default' => 1,
        'type'       => 'lead',
    ]);

    // Create test stages
    test()->startStage = Stage::create([
        'code'             => 'nieuwe-aanvraag-kwalificeren',
        'name'             => 'Nieuwe aanvraag',
        'probability'      => 100,
        'sort_order'       => 1,
        'lead_pipeline_id' => test()->pipeline->id,
    ]);

    test()->followUpStage = Stage::create([
        'code'             => 'klant-adviseren-start',
        'name'             => 'Klant adviseren start',
        'probability'      => 100,
        'sort_order'       => 2,
        'lead_pipeline_id' => test()->pipeline->id,
    ]);

    // Create a test lead
    test()->lead = Lead::create([
        'first_name'             => 'John',
        'last_name'              => 'Doe',
        'lead_pipeline_id'       => test()->pipeline->id,
        'lead_pipeline_stage_id' => test()->startStage->id,
        'user_id'                => test()->user->id,
    ]);

    // Configure the validation rules
    LeadStatusTransitionValidator::addTransitionRule(
        'klant-adviseren-start',
        'klant-adviseren-opvolgen',
        [
            'min_persons' => 1,
            'message'     => 'Voor de status "Klant adviseren opvolgen" moet minimaal 1 persoon aan de lead gekoppeld zijn.',
        ]
    );

    // Configure validation rule for first stage transition (align with defaults)
    LeadStatusTransitionValidator::addTransitionRule(
        'nieuwe-aanvraag-kwalificeren',
        'klant-adviseren-start',
        [
            'min_persons'     => 1,
            'required_fields' => ['first_name', 'last_name'],
            'message'         => 'Voor de status "Klant adviseren" moet minimaal 1 persoon aan de lead gekoppeld zijn.',
        ]
    );
});

test('it blocks transition when no persons are attached', function () {
    // Lead has no persons attached
    expect(test()->lead->persons_count)->toBe(0)
        ->and(fn () => LeadStatusTransitionValidator::validateTransition(test()->lead, test()->followUpStage->id))
        ->toThrow(ValidationException::class);

    // Attempt to transition should fail
});

test('it allows transition when persons are attached', function () {
    // Create and attach a person to the lead
    $person = Person::create([
        'name'   => 'Jane Doe',
        'emails' => [['value' => 'jane@example.com', 'is_default' => true]],
    ]);

    test()->lead->attachPersons([$person->id]);

    // Refresh the lead to get updated persons_count
    test()->lead->refresh();

    expect(test()->lead->persons_count)->toBe(1);

    // Transition should succeed
    LeadStatusTransitionValidator::validateTransition(test()->lead, test()->followUpStage->id);
});

test('it allows transition when multiple persons are attached', function () {
    // Create and attach multiple persons to the lead
    $person1 = Person::create([
        'name'   => 'Jane Doe',
        'emails' => [['value' => 'jane@example.com', 'is_default' => true]],
    ]);

    $person2 = Person::create([
        'name'   => 'Bob Smith',
        'emails' => [['value' => 'bob@example.com', 'is_default' => true]],
    ]);

    test()->lead->attachPersons([$person1->id, $person2->id]);

    // Refresh the lead to get updated persons_count
    test()->lead->refresh();

    expect(test()->lead->persons_count)->toBe(2);

    // Transition should succeed
    LeadStatusTransitionValidator::validateTransition(test()->lead, test()->followUpStage->id);
});

test('it ignores validation for transitions without rules', function () {
    // Create a stage without validation rules
    $otherStage = Stage::create([
        'code'             => 'other-stage',
        'name'             => 'Other Stage',
        'probability'      => 100,
        'sort_order'       => 3,
        'lead_pipeline_id' => test()->lead->pipeline->id,
    ]);

    // Transition should succeed even without persons
    LeadStatusTransitionValidator::validateTransition(test()->lead, $otherStage->id);
    expect(true)->toBeTrue();
});

test('it works with lead model update method', function () {
    // Lead has no persons attached
    expect(test()->lead->persons_count)->toBe(0)
        ->and(fn () => test()->lead->update(['lead_pipeline_stage_id' => test()->followUpStage->id]))
        ->toThrow(ValidationException::class);

    // Attempt to update stage should fail
});

test('it works with lead model update stage method', function () {
    // Lead has no persons attached
    expect(test()->lead->persons_count)->toBe(0)
        ->and(fn () => test()->lead->updateStage(test()->followUpStage->id))
        ->toThrow(ValidationException::class);

    // Attempt to update stage should fail
});

test('it can add and remove transition rules', function () {
    // Remove the existing rule
    LeadStatusTransitionValidator::removeTransitionRule(
        'nieuwe-aanvraag-kwalificeren',
        'klant-adviseren-start'
    );

    // Now transition should succeed even without persons
    LeadStatusTransitionValidator::validateTransition(test()->lead, test()->followUpStage->id);

    // Add the rule back
    LeadStatusTransitionValidator::addTransitionRule(
        'nieuwe-aanvraag-kwalificeren',
        'klant-adviseren-start',
        [
            'min_persons'     => 1,
            'required_fields' => ['first_name', 'last_name'],
            'message'         => 'Test message',
        ]
    );

    // Now transition should fail again
    expect(fn () => LeadStatusTransitionValidator::validateTransition(test()->lead, test()->followUpStage->id))
        ->toThrow(ValidationException::class);
});

test('it validates required fields for first stage transition', function () {
    // Create a lead without first_name and last_name
    $incompleteLead = Lead::create([
        'lead_pipeline_id'       => test()->pipeline->id,
        'lead_pipeline_stage_id' => test()->startStage->id,
    ]);

    // Attempt to transition should fail due to missing required fields
    expect(fn () => LeadStatusTransitionValidator::validateTransition($incompleteLead, test()->followUpStage->id))
        ->toThrow(ValidationException::class);

    // Now add the required fields
    $incompleteLead->update([
        'first_name' => 'John',
        'last_name'  => 'Doe',
    ]);

    // Also attach the required minimum persons (1)
    $person = Person::create([
        'name'   => 'Alice Doe',
        'emails' => [['value' => 'alice@example.com', 'is_default' => true]],
    ]);
    $incompleteLead->attachPersons([$person->id]);
    $incompleteLead->refresh();

    // Transition should now succeed
    LeadStatusTransitionValidator::validateTransition($incompleteLead, test()->followUpStage->id);
});
