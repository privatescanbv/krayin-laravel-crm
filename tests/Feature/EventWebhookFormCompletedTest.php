<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\FormStatus;
use App\Enums\FormType;
use App\Events\PatientFormCompletedEvent;
use App\Events\PatientFormStatusUpdatedEvent;
use App\Listeners\CreateFormReviewTask;
use App\Listeners\UpdateAnamnesisFormStatus;
use App\Models\Anamnesis;
use App\Models\Order;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('api.keys', ['valid-api-key-123']);
});

test('PUT webhooks/event with completed status dispatches PatientFormCompletedEvent and PatientFormStatusUpdatedEvent', function () {
    Event::fake([PatientFormCompletedEvent::class, PatientFormStatusUpdatedEvent::class]);

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

    Event::assertDispatched(PatientFormStatusUpdatedEvent::class, function ($event) {
        return $event->formId === 'form-abc-123' && $event->status === FormStatus::Completed;
    });
});

test('PUT webhooks/event with step1 status dispatches only PatientFormStatusUpdatedEvent', function () {
    Event::fake([PatientFormCompletedEvent::class, PatientFormStatusUpdatedEvent::class]);

    $person = Person::factory()->create();

    $response = $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->putJson('/api/webhooks/event', [
            'entity_type' => 'forms',
            'id'          => 'form-abc-456',
            'action'      => 'STATUS_UPDATE',
            'status'      => 'step1',
            'url'         => 'https://forms.example.com/form-abc-456',
            'person_id'   => $person->id,
            'form_type'   => 'privatescan',
        ]);

    $response->assertOk();

    Event::assertNotDispatched(PatientFormCompletedEvent::class);
    Event::assertDispatched(PatientFormStatusUpdatedEvent::class, function ($event) {
        return $event->formId === 'form-abc-456' && $event->status === FormStatus::Step1_completed;
    });
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

test('CreateFormReviewTask listener links task activity to active order before lead from anamnesis gvl form link', function () {
    $this->seed(TestSeeder::class);

    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create();
    $stage = Stage::factory()->create([
        'is_won'  => false,
        'is_lost' => false,
    ]);
    $order = Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => $stage->id,
    ]);
    $formId = 'form-lead-123';

    Anamnesis::factory()->create([
        'gvl_form_id' => $formId,
        'lead_id'     => $lead->id,
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
        ->and($activity->order_id)->toBe($order->id)
        ->and($activity->lead_id)->toBeNull()
        ->and($activity->sales_lead_id)->toBeNull();
});

test('CreateFormReviewTask listener links task activity to active order before sales lead from anamnesis gvl form link', function () {
    $this->seed(TestSeeder::class);

    $person = Person::factory()->create();
    $salesLead = SalesLead::factory()->create();
    $stage = Stage::factory()->create([
        'is_won'  => false,
        'is_lost' => false,
    ]);
    $order = Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => $stage->id,
    ]);
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
        ->and($activity->order_id)->toBe($order->id)
        ->and($activity->sales_lead_id)->toBeNull()
        ->and($activity->lead_id)->toBeNull();
});

test('CreateFormReviewTask listener falls back to lead_id when no active order exists', function () {
    $this->seed(TestSeeder::class);

    $person = Person::factory()->create();
    $salesLead = SalesLead::factory()->create();
    $lead = Lead::factory()->create();
    $lostStage = Stage::factory()->lost()->create();
    Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => $lostStage->id,
    ]);
    $formId = 'form-sales-fallback-123';

    Anamnesis::factory()->create([
        'gvl_form_id' => $formId,
        'lead_id'     => $lead->id,
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
        ->and($activity->lead_id)->toBe($lead->id)
        ->and($activity->order_id)->toBeNull()
        ->and($activity->sales_lead_id)->toBeNull();
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

test('UpdateAnamnesisFormStatus listener updates gvl_form_status on matching anamnesis', function () {
    $lead = Lead::factory()->create();
    $formId = 'form-status-123';

    $anamnesis = Anamnesis::factory()->create([
        'gvl_form_id'     => $formId,
        'gvl_form_status' => FormStatus::New,
        'lead_id'         => $lead->id,
        'sales_id'        => null,
    ]);

    $event = new PatientFormStatusUpdatedEvent($formId, FormStatus::Step2_completed, FormType::PrivateScan);
    $listener = app(UpdateAnamnesisFormStatus::class);
    $listener->handle($event);

    expect($anamnesis->fresh()->gvl_form_status)->toBe(FormStatus::Step2_completed);
});

test('UpdateAnamnesisFormStatus listener sets status to completed', function () {
    $lead = Lead::factory()->create();
    $formId = 'form-done-456';

    $anamnesis = Anamnesis::factory()->create([
        'gvl_form_id'     => $formId,
        'gvl_form_status' => FormStatus::Step3_completed,
        'lead_id'         => $lead->id,
        'sales_id'        => null,
    ]);

    $event = new PatientFormStatusUpdatedEvent($formId, FormStatus::Completed, FormType::PrivateScan);
    $listener = app(UpdateAnamnesisFormStatus::class);
    $listener->handle($event);

    expect($anamnesis->fresh()->gvl_form_status)->toBe(FormStatus::Completed);
});

test('UpdateAnamnesisFormStatus listener logs error when no anamnesis found', function () {
    Log::shouldReceive('info')->zeroOrMoreTimes();
    Log::shouldReceive('error')
        ->once()
        ->with('UpdateAnamnesisFormStatus: geen anamnese gevonden voor formulier', Mockery::on(fn ($ctx) => $ctx['form_id'] === 'form-missing-789'));

    $event = new PatientFormStatusUpdatedEvent('form-missing-789', FormStatus::Step1_completed, FormType::PrivateScan);
    $listener = app(UpdateAnamnesisFormStatus::class);
    $listener->handle($event);
});
