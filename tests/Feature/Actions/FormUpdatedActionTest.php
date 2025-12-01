<?php

namespace Tests\Feature\Actions;

use App\Actions\Forms\FormUpdatedAction;
use App\Models\Anamnesis;
use App\Models\SalesLead;
use App\Services\FormService;
use App\Services\Mail\EmailRenderingService;
use App\Services\Mail\PatientMailService;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    Mail::fake();
    config(['mail.send_only_accept' => '*@example.com']);
    Log::spy();
});

test('FormUpdatedAction stores email with lead_id when lead is found', function () {
    $person = Person::factory()->create([
        'emails' => [['value' => 'test@example.com', 'is_default' => true]],
    ]);

    $lead = Lead::factory()->create();

    // Mock FormService to return lead
    $mockFormService = Mockery::mock(FormService::class);
    $mockFormService->shouldReceive('findRelatedEntityByFormId')
        ->once()
        ->with('https://forms.example.com/forms/1')
        ->andReturn([
            'lead'      => $lead,
            'sales'     => null,
            'person_id' => $person->id,
        ]);

    // Mock PersonRepository
    $mockPersonRepository = Mockery::mock(PersonRepository::class);
    $mockPersonRepository->shouldReceive('find')
        ->once()
        ->with($person->id)
        ->andReturn($person);

    // Use real PatientMailService to test actual email creation
    $patientMailService = app(PatientMailService::class);

    $action = new FormUpdatedAction(
        $patientMailService,
        $mockFormService,
        $mockPersonRepository,
        app(EmailRenderingService::class)
    );

    $result = $action->execute('123', 'completed', 'https://forms.example.com/forms/1');

    expect($result['success'])->toBeTrue()
        ->and($result['message'])->toBe('Mail has been send');

    // Verify email was created with lead_id
    $emailRecord = Email::where('lead_id', $lead->id)
        ->where('person_id', $person->id)
        ->first();

    expect($emailRecord)->not->toBeNull()
        ->and($emailRecord->lead_id)->toBe($lead->id)
        ->and($emailRecord->person_id)->toBe($person->id)
        ->and($emailRecord->subject)->toBe('Welkom bij het Privatescan patiëntportaal');
});

test('FormUpdatedAction stores email with sales_lead_id when sales is found', function () {
    $person = Person::factory()->create([
        'emails' => [['value' => 'test@example.com', 'is_default' => true]],
    ]);

    $salesLead = SalesLead::factory()->create();

    // Mock FormService to return sales lead
    $mockFormService = Mockery::mock(FormService::class);
    $mockFormService->shouldReceive('findRelatedEntityByFormId')
        ->once()
        ->with('https://forms.example.com/forms/2')
        ->andReturn([
            'lead'      => null,
            'sales'     => $salesLead,
            'person_id' => $person->id,
        ]);

    // Mock PersonRepository
    $mockPersonRepository = Mockery::mock(PersonRepository::class);
    $mockPersonRepository->shouldReceive('find')
        ->once()
        ->with($person->id)
        ->andReturn($person);

    // Use real PatientMailService to test actual email creation
    $patientMailService = app(PatientMailService::class);

    $action = new FormUpdatedAction(
        $patientMailService,
        $mockFormService,
        $mockPersonRepository,
        app(EmailRenderingService::class)
    );

    $result = $action->execute('456', 'completed', 'https://forms.example.com/forms/2');

    expect($result['success'])->toBeTrue();

    // Verify email was created with sales_lead_id
    $emailRecord = Email::where('sales_lead_id', $salesLead->id)
        ->where('person_id', $person->id)
        ->first();

    expect($emailRecord)->not->toBeNull()
        ->and($emailRecord->sales_lead_id)->toBe($salesLead->id)
        ->and($emailRecord->person_id)->toBe($person->id)
        ->and($emailRecord->lead_id)->toBeNull();
});

test('FormUpdatedAction stores email with only person_id when neither lead nor sales is found', function () {
    $person = Person::factory()->create([
        'emails' => [['value' => 'test@example.com', 'is_default' => true]],
    ]);

    // Mock FormService to return no lead or sales
    $mockFormService = Mockery::mock(FormService::class);
    $mockFormService->shouldReceive('findRelatedEntityByFormId')
        ->once()
        ->with('https://forms.example.com/forms/3')
        ->andReturn([
            'lead'      => null,
            'sales'     => null,
            'person_id' => $person->id,
        ]);

    // Mock PersonRepository
    $mockPersonRepository = Mockery::mock(PersonRepository::class);
    $mockPersonRepository->shouldReceive('find')
        ->once()
        ->with($person->id)
        ->andReturn($person);

    // Use real PatientMailService to test actual email creation
    $patientMailService = app(PatientMailService::class);

    $action = new FormUpdatedAction(
        $patientMailService,
        $mockFormService,
        $mockPersonRepository,
        app(EmailRenderingService::class)
    );

    $result = $action->execute('789', 'completed', 'https://forms.example.com/forms/3');

    expect($result['success'])->toBeTrue();

    // Verify email was created with only person_id
    $emailRecord = Email::where('person_id', $person->id)
        ->whereNull('lead_id')
        ->whereNull('sales_lead_id')
        ->first();

    expect($emailRecord)->not->toBeNull()
        ->and($emailRecord->person_id)->toBe($person->id)
        ->and($emailRecord->lead_id)->toBeNull()
        ->and($emailRecord->sales_lead_id)->toBeNull();
});

test('FormUpdatedAction does not send email when status is not completed', function () {
    $mockFormService = Mockery::mock(FormService::class);
    $mockFormService->shouldNotReceive('findRelatedEntityByFormId');

    $mockPersonRepository = Mockery::mock(PersonRepository::class);
    $mockPersonRepository->shouldNotReceive('find');

    $mockPatientMailService = Mockery::mock(PatientMailService::class);
    $mockPatientMailService->shouldNotReceive('mailPatient');

    $action = new FormUpdatedAction(
        $mockPatientMailService,
        $mockFormService,
        $mockPersonRepository,
        app(EmailRenderingService::class)
    );

    $result = $action->execute('123', 'pending', 'https://forms.example.com/forms/1');

    expect($result['success'])->toBeTrue()
        ->and($result['message'])->toBe('Mail has been send');

    // Verify no email was created
    $emailCount = Email::count();
    expect($emailCount)->toBe(0);
});

test('FormUpdatedAction throws exception when person_id is not found', function () {
    $mockFormService = Mockery::mock(FormService::class);
    $mockFormService->shouldReceive('findRelatedEntityByFormId')
        ->once()
        ->with('https://forms.example.com/forms/1')
        ->andReturn([
            'lead'      => null,
            'sales'     => null,
            'person_id' => null, // No person_id found
        ]);

    $mockPersonRepository = Mockery::mock(PersonRepository::class);
    $mockPatientMailService = Mockery::mock(PatientMailService::class);

    $action = new FormUpdatedAction(
        $mockPatientMailService,
        $mockFormService,
        $mockPersonRepository,
        app(EmailRenderingService::class)
    );

    expect(fn () => $action->execute('123', 'completed', 'https://forms.example.com/forms/1'))
        ->toThrow(\RuntimeException::class, 'Geen persoon gekoppeld aan het formulier');
});

test('FormUpdatedAction happy flow: stores email with lead_id and person_id from real anamnesis and active lead', function () {
    // Create person with email
    $person = Person::factory()->create([
        'emails' => [['value' => 'test@example.com', 'is_default' => true]],
    ]);

    // Create an open stage (not won/lost)
    $stage = Stage::where('is_won', false)
        ->where('is_lost', false)
        ->first() ?? Stage::factory()->create([
            'is_won'  => false,
            'is_lost' => false,
        ]);

    // Create active lead with open stage
    $lead = Lead::factory()->create([
        'lead_pipeline_stage_id' => $stage->id,
    ]);

    // Link person to lead via lead_persons pivot table
    $lead->persons()->attach($person->id);

    // Create anamnesis with gvl_form_link
    $formUrl = 'https://forms.example.com/forms/123';
    $anamnesis = Anamnesis::factory()->create([
        'lead_id'       => $lead->id,
        'person_id'     => $person->id,
        'gvl_form_link' => $formUrl,
    ]);

    // Use real services (no mocks)
    $action = app(FormUpdatedAction::class);

    $result = $action->execute('123', 'completed', $formUrl);

    expect($result['success'])->toBeTrue()
        ->and($result['message'])->toBe('Mail has been send');

    // Verify email was created with correct lead_id and person_id
    $emailRecord = Email::where('lead_id', $lead->id)
        ->where('person_id', $person->id)
        ->first();

    expect($emailRecord)->not->toBeNull()
        ->and($emailRecord->lead_id)->toBe($lead->id)
        ->and($emailRecord->person_id)->toBe($person->id)
        ->and($emailRecord->subject)->toBe('Welkom bij het Privatescan patiëntportaal')
        ->and($emailRecord->sales_lead_id)->toBeNull();
});
