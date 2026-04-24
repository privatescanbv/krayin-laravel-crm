<?php

use App\Models\Clinic;
use App\Models\SalesLead;
use App\Services\Mail\GraphMailService;
use App\Services\Mail\MicrosoftGraphTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Contact\Models\Person;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Email\Repositories\EmailRepository;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['mail.graph.client_id' => 'test-client-id']);
    config(['mail.graph.client_secret' => 'test-client-secret']);
    config(['mail.graph.tenant_id' => 'test-tenant-id']);
    config(['mail.graph.mailbox' => 'test@example.com']);
    config(['mail.graph.sender_domain' => 'example.com']);

    $emailRepository = test()->createMock(EmailRepository::class);
    $attachmentRepository = test()->createMock(AttachmentRepository::class);

    $this->processor = new GraphMailService(
        $emailRepository,
        $attachmentRepository,
        new MicrosoftGraphTokenService,
    );

    $this->method = new ReflectionMethod($this->processor, 'linkToExistingEntities');
    $this->method->setAccessible(true);
});

function callLink(array $emailData, string $emailAddress): array
{
    return test()->method->invoke(test()->processor, $emailData, $emailAddress);
}

function createActiveStage(): Stage
{
    $pipeline = Pipeline::first() ?? Pipeline::create([
        'name'        => 'Default Pipeline',
        'is_default'  => 1,
        'rotten_days' => 30,
    ]);

    return Stage::factory()->create(['lead_pipeline_id' => $pipeline->id]);
}

function createWonStage(): Stage
{
    $pipeline = Pipeline::first() ?? Pipeline::create([
        'name'        => 'Default Pipeline',
        'is_default'  => 1,
        'rotten_days' => 30,
    ]);

    return Stage::factory()->won()->create(['lead_pipeline_id' => $pipeline->id]);
}

function createLostStage(): Stage
{
    $pipeline = Pipeline::first() ?? Pipeline::create([
        'name'        => 'Default Pipeline',
        'is_default'  => 1,
        'rotten_days' => 30,
    ]);

    return Stage::factory()->lost()->create(['lead_pipeline_id' => $pipeline->id]);
}

test('returns email data unchanged when address is empty', function () {
    $emailData = ['subject' => 'Test'];

    $result = callLink($emailData, '');

    expect($result)->toEqual($emailData);
});

test('returns email data unchanged when no match found', function () {
    $emailData = ['subject' => 'Test'];

    $result = callLink($emailData, 'nobody@unknown.com');

    expect($result)->toEqual($emailData);
});

test('links person by email', function () {
    $person = Person::factory()->create([
        'emails' => [['value' => 'patient@example.com', 'is_default' => true]],
    ]);

    $result = callLink(['subject' => 'Test'], 'patient@example.com');

    expect($result['person_id'])->toEqual($person->id);
});

test('links active sales lead via person', function () {
    $activeStage = createActiveStage();

    $person = Person::factory()->create([
        'emails' => [['value' => 'patient@example.com', 'is_default' => true]],
    ]);

    $salesLead = SalesLead::factory()->create([
        'pipeline_stage_id' => $activeStage->id,
    ]);
    $salesLead->persons()->attach($person->id);

    $result = callLink(['subject' => 'Test'], 'patient@example.com');

    expect($result['person_id'])->toEqual($person->id)
        ->and($result['sales_lead_id'])->toEqual($salesLead->id);
});

test('skips won sales lead', function () {
    $wonStage = createWonStage();

    $person = Person::factory()->create([
        'emails' => [['value' => 'patient@example.com', 'is_default' => true]],
    ]);

    $salesLead = SalesLead::factory()->create([
        'pipeline_stage_id' => $wonStage->id,
    ]);
    $salesLead->persons()->attach($person->id);

    $result = callLink(['subject' => 'Test'], 'patient@example.com');

    expect($result['person_id'])->toEqual($person->id)
        ->and($result)->not->toHaveKey('sales_lead_id');
});

test('skips lost sales lead', function () {
    $lostStage = createLostStage();

    $person = Person::factory()->create([
        'emails' => [['value' => 'patient@example.com', 'is_default' => true]],
    ]);

    $salesLead = SalesLead::factory()->create([
        'pipeline_stage_id' => $lostStage->id,
    ]);
    $salesLead->persons()->attach($person->id);

    $result = callLink(['subject' => 'Test'], 'patient@example.com');

    expect($result['person_id'])->toEqual($person->id)
        ->and($result)->not->toHaveKey('sales_lead_id');
});

test('links newest active sales lead', function () {
    $activeStage = createActiveStage();

    $person = Person::factory()->create([
        'emails' => [['value' => 'patient@example.com', 'is_default' => true]],
    ]);

    $olderSalesLead = SalesLead::factory()->create([
        'pipeline_stage_id' => $activeStage->id,
        'created_at'        => now()->subDays(5),
    ]);
    $olderSalesLead->persons()->attach($person->id);

    $newerSalesLead = SalesLead::factory()->create([
        'pipeline_stage_id' => $activeStage->id,
        'created_at'        => now(),
    ]);
    $newerSalesLead->persons()->attach($person->id);

    $result = callLink(['subject' => 'Test'], 'patient@example.com');

    expect($result['sales_lead_id'])->toEqual($newerSalesLead->id);
});

test('links active lead via person', function () {
    $activeStage = createActiveStage();

    $person = Person::factory()->create([
        'emails' => [['value' => 'patient@example.com', 'is_default' => true]],
    ]);

    $lead = Lead::factory()->create([
        'lead_pipeline_stage_id' => $activeStage->id,
    ]);
    $lead->persons()->attach($person->id);

    $result = callLink(['subject' => 'Test'], 'patient@example.com');

    expect($result['person_id'])->toEqual($person->id)
        ->and($result['lead_id'])->toEqual($lead->id);
});

test('skips won lead', function () {
    $wonStage = createWonStage();

    $person = Person::factory()->create([
        'emails' => [['value' => 'patient@example.com', 'is_default' => true]],
    ]);

    $lead = Lead::factory()->create([
        'lead_pipeline_stage_id' => $wonStage->id,
    ]);
    $lead->persons()->attach($person->id);

    $result = callLink(['subject' => 'Test'], 'patient@example.com');

    expect($result['person_id'])->toEqual($person->id)
        ->and($result)->not->toHaveKey('lead_id');
});

test('links lead by email when no person found', function () {
    $activeStage = createActiveStage();

    $lead = Lead::factory()->create([
        'emails'                 => [['value' => 'directlead@example.com', 'is_default' => true]],
        'lead_pipeline_stage_id' => $activeStage->id,
    ]);

    $result = callLink(['subject' => 'Test'], 'directlead@example.com');

    expect($result)->not->toHaveKey('person_id')
        ->and($result['lead_id'])->toEqual($lead->id);
});

test('skips won lead by email', function () {
    $wonStage = createWonStage();

    Lead::factory()->create([
        'emails'                 => [['value' => 'directlead@example.com', 'is_default' => true]],
        'lead_pipeline_stage_id' => $wonStage->id,
    ]);

    $result = callLink(['subject' => 'Test'], 'directlead@example.com');

    expect($result)->not->toHaveKey('lead_id');
});

test('links clinic by email', function () {
    $clinic = Clinic::factory()->create([
        'emails' => ['clinic@hospital.com'],
    ]);

    $result = callLink(['subject' => 'Test'], 'clinic@hospital.com');

    expect($result['clinic_id'])->toEqual($clinic->id);
});

test('links person and clinic simultaneously', function () {
    $email = 'shared@hospital.com';

    $person = Person::factory()->create([
        'emails' => [['value' => $email, 'is_default' => true]],
    ]);

    $clinic = Clinic::factory()->create([
        'emails' => [$email],
    ]);

    $result = callLink(['subject' => 'Test'], $email);

    expect($result['person_id'])->toEqual($person->id)
        ->and($result['clinic_id'])->toEqual($clinic->id);
});

test('links all entity types simultaneously', function () {
    $activeStage = createActiveStage();
    $email = 'patient@example.com';

    $person = Person::factory()->create([
        'emails' => [['value' => $email, 'is_default' => true]],
    ]);

    $lead = Lead::factory()->create([
        'lead_pipeline_stage_id' => $activeStage->id,
    ]);
    $lead->persons()->attach($person->id);

    $salesLead = SalesLead::factory()->create([
        'pipeline_stage_id' => $activeStage->id,
    ]);
    $salesLead->persons()->attach($person->id);

    $clinic = Clinic::factory()->create([
        'emails' => [$email],
    ]);

    $result = callLink(['subject' => 'Test'], $email);

    expect($result['person_id'])->toEqual($person->id)
        ->and($result['sales_lead_id'])->toEqual($salesLead->id)
        ->and($result['lead_id'])->toEqual($lead->id)
        ->and($result['clinic_id'])->toEqual($clinic->id);
});

test('preserves existing email data', function () {
    $emailData = [
        'subject'    => 'Test Subject',
        'from'       => ['name' => 'Test', 'email' => 'test@example.com'],
        'message_id' => 'abc123',
    ];

    $result = callLink($emailData, 'nobody@unknown.com');

    expect($result['subject'])->toEqual('Test Subject')
        ->and($result['message_id'])->toEqual('abc123');
});
