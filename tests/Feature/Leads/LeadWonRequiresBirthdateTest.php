<?php

use App\Enums\PipelineStage;
use App\Services\LeadStatusTransitionValidator;
use Database\Seeders\TestSeeder;
use Illuminate\Validation\ValidationException;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    LeadStatusTransitionValidator::reset();

    test()->user = User::factory()->create();
    test()->actingAs(test()->user);

    test()->pipeline = Pipeline::whereType('lead')->firstOrFail();
    test()->startStage = Stage::whereCode(PipelineStage::NIEUWE_AANVRAAG_KWALIFICEREN)->firstOrFail();
    test()->wonStage = Stage::whereCode(PipelineStage::WON)->firstOrFail();

    test()->lead = Lead::create([
        'first_name'             => 'John',
        'last_name'              => 'Doe',
        'emails'                 => [['value' => 'john.doe@example.com', 'is_default' => true]],
        'lead_pipeline_id'       => test()->pipeline->id,
        'lead_pipeline_stage_id' => test()->startStage->id,
        'user_id'                => test()->user->id,
    ]);
});

test('it blocks transition to won when the perfectly matched person has no date of birth', function () {
    $person = Person::create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'emails'     => [['value' => 'john.doe@example.com', 'is_default' => true]],
    ]);

    test()->lead->attachPersons([$person->id]);

    expect(LeadStatusTransitionValidator::calculateMatchScore(test()->lead, $person))->toBe(100.0)
        ->and(fn () => LeadStatusTransitionValidator::validateTransition(test()->lead, test()->wonStage->id))
        ->toThrow(ValidationException::class, 'geboortedatum');
});

test('it allows transition to won when the perfectly matched person has a date of birth', function () {
    $person = Person::create([
        'first_name'    => 'John',
        'last_name'     => 'Doe',
        'emails'        => [['value' => 'john.doe@example.com', 'is_default' => true]],
        'date_of_birth' => '1990-01-01',
    ]);

    test()->lead->attachPersons([$person->id]);

    LeadStatusTransitionValidator::validateTransition(test()->lead, test()->wonStage->id);

    expect(true)->toBeTrue();
});
