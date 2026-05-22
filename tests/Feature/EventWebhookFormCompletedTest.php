<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\FormType;
use App\Events\PatientFormCompletedEvent;
use App\Listeners\CreateFormReviewTask;
use App\Models\Anamnesis;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('api.keys', ['valid-api-key-123']);
});

test('PUT webhooks/event with completed status dispatches PatientFormCompletedEvent', function () {
    Event::fake([PatientFormCompletedEvent::class]);

    $person = Person::factory()->create();

    $response = $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson('/api/webhooks/event', [
            'entity_type' => 'forms',
            'id'          => 'form-abc-123',
            'action'      => 'STATUS_UPDATE',
            'status'      => 'completed',
            'url'         => 'https://forms.example.com/form-abc-123',
            'person_id'   => $person->id,
            'form_type'   => 'privatescan',
        ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);

    Event::assertDispatched(PatientFormCompletedEvent::class, function ($event) use ($person) {
        return $event->formId === 'form-abc-123' && $event->person->id === $person->id;
    });
});

test('PUT webhooks/event with non-completed status does not dispatch event', function () {
    Event::fake([PatientFormCompletedEvent::class]);

    $person = Person::factory()->create();

    $response = $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson('/api/webhooks/event', [
            'entity_type' => 'forms',
            'id'          => 'form-abc-456',
            'action'      => 'STATUS_UPDATE',
            'status'      => 'in_progress',
            'url'         => 'https://forms.example.com/form-abc-456',
            'person_id'   => $person->id,
            'form_type'   => 'privatescan',
        ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);

    Event::assertNotDispatched(PatientFormCompletedEvent::class);
});

test('PUT webhooks/event returns 422 when required fields are missing', function () {
    $response = $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson('/api/webhooks/event', []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['entity_type', 'id', 'action', 'status', 'url', 'person_id', 'form_type']);
});

test('PUT webhooks/event returns 422 when person_id does not exist', function () {
    $response = $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson('/api/webhooks/event', [
            'entity_type' => 'forms',
            'id'          => 'form-abc-123',
            'action'      => 'STATUS_UPDATE',
            'status'      => 'completed',
            'url'         => 'https://forms.example.com/form-abc-123',
            'person_id'   => 99999,
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['person_id']);
});

test('CreateFormReviewTask listener creates task activity with 5-day deadline', function () {
    $this->seed(TestSeeder::class);

    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $formId = 'form-abc-123';

    Anamnesis::factory()->create([
        'gvl_form_id' => $formId,
        'lead_id'     => $lead->id,
        'person_id'   => $person->id,
        'sales_id'    => null,
    ]);

    $event = new PatientFormCompletedEvent($person, $formId, FormType::PrivateScan);
    $listener = app(CreateFormReviewTask::class);
    $listener->handle($event);

    $activity = Activity::where('person_id', $person->id)
        ->where('type', ActivityType::TASK->value)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->title)->toBe('GVL controleren')
        ->and($activity->is_done)->toBeFalse()
        ->and($activity->status)->toBe(ActivityStatus::ACTIVE)
        ->and($activity->additional)->toMatchArray(['form_id' => $formId])
        ->and($activity->schedule_to->diffInDays(now(), true))->toBeGreaterThanOrEqual(4)->toBeLessThanOrEqual(5);
});

test('CreateFormReviewTask listener links task activity to lead from anamnesis gvl form link', function () {
    $this->seed(TestSeeder::class);

    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $formId = 'form-lead-123';

    Anamnesis::factory()->create([
        'gvl_form_id' => $formId,
        'lead_id'     => $lead->id,
        'person_id'   => $person->id,
        'sales_id'    => null,
    ]);

    $event = new PatientFormCompletedEvent($person, $formId, FormType::PrivateScan);
    $listener = app(CreateFormReviewTask::class);
    $listener->handle($event);

    $activity = Activity::where('person_id', $person->id)
        ->where('type', ActivityType::TASK->value)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->lead_id)->toBe($lead->id)
        ->and($activity->sales_lead_id)->toBeNull();
});

test('CreateFormReviewTask listener links task activity to sales lead from anamnesis gvl form link', function () {
    $this->seed(TestSeeder::class);

    $person = Person::factory()->create();
    $salesLead = SalesLead::factory()->create();
    $formId = 'form-sales-123';

    Anamnesis::factory()->create([
        'gvl_form_id' => $formId,
        'lead_id'     => null,
        'person_id'   => $person->id,
        'sales_id'    => $salesLead->id,
    ]);

    $event = new PatientFormCompletedEvent($person, $formId, FormType::PrivateScan);
    $listener = app(CreateFormReviewTask::class);
    $listener->handle($event);

    $activity = Activity::where('person_id', $person->id)
        ->where('type', ActivityType::TASK->value)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->sales_lead_id)->toBe($salesLead->id)
        ->and($activity->lead_id)->toBeNull();
});

test('CreateFormReviewTask listener logs error and skips activity when no anamnesis found', function () {
    Log::shouldReceive('info')->zeroOrMoreTimes();
    Log::shouldReceive('warning')->zeroOrMoreTimes();
    Log::shouldReceive('error')
        ->once()
        ->with('CreateFormReviewTask: geen anamnese gevonden voor GVL formulier', ['form_id' => 'form-without-anamnesis-123']);

    $person = Person::factory()->create();
    $formId = 'form-without-anamnesis-123';

    $event = new PatientFormCompletedEvent($person, $formId, FormType::PrivateScan);
    $listener = app(CreateFormReviewTask::class);
    $listener->handle($event);

    expect(Activity::where('person_id', $person->id)->where('type', ActivityType::TASK->value)->exists())->toBeFalse();
});
