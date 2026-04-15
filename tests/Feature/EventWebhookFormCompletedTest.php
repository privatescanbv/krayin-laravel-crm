<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\FormType;
use App\Events\PatientFormCompletedEvent;
use App\Listeners\CreateFormReviewTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;

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
    $person = Person::factory()->create();
    $formId = 'form-abc-123';

    $event = new PatientFormCompletedEvent($person, $formId, FormType::PrivateScan);
    $listener = app(CreateFormReviewTask::class);
    $listener->handle($event);

    $activity = Activity::where('person_id', $person->id)
        ->where('type', ActivityType::TASK->value)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->title)->toBe('Formulier controleren')
        ->and($activity->is_done)->toBeFalse()
        ->and($activity->status)->toBe(ActivityStatus::ACTIVE)
        ->and($activity->additional)->toMatchArray(['form_id' => $formId])
        ->and($activity->schedule_to->diffInDays(now(), true))->toBeGreaterThanOrEqual(4)->toBeLessThanOrEqual(5);
});
