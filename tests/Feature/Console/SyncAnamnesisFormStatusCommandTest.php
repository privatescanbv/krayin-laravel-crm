<?php

namespace Tests\Feature\Console;

use App\Enums\ActivityType;
use App\Enums\FormStatus;
use App\Events\PatientFormCompletedEvent;
use App\Events\PatientFormStatusUpdatedEvent;
use App\Models\Anamnesis;
use App\Models\AnamnesisGvlForm;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    config([
        'services.portal.patient.api_url'   => 'http://forms',
        'services.portal.patient.api_token' => 'test-token',
    ]);
});

function makeGvlForm(string $formId, FormStatus $status, array $anamnesisOverrides = []): AnamnesisGvlForm
{
    $anamnesis = Anamnesis::factory()->create(array_merge([
        'lead_id'  => Lead::factory(),
        'sales_id' => null,
    ], $anamnesisOverrides));

    return AnamnesisGvlForm::create([
        'anamnesis_id'    => $anamnesis->id,
        'gvl_form_id'     => $formId,
        'gvl_form_status' => $status,
    ]);
}

test('it updates gvl_form_status when the forms app reports a newer status', function () {
    $form = makeGvlForm('101', FormStatus::Step1_completed);

    Http::fake(['http://forms/api/forms/101/status' => Http::response(['status' => 'step2'], 200)]);

    $this->artisan('forms:sync-anamnesis-status')->assertSuccessful();

    expect($form->fresh()->gvl_form_status)->toBe(FormStatus::Step2_completed);
});

test('it does nothing when the reported status is unchanged', function () {
    Event::fake([PatientFormStatusUpdatedEvent::class, PatientFormCompletedEvent::class]);

    makeGvlForm('102', FormStatus::Step2_completed);

    Http::fake(['http://forms/api/forms/102/status' => Http::response(['status' => 'step2'], 200)]);

    $this->artisan('forms:sync-anamnesis-status')->assertSuccessful();

    Event::assertNotDispatched(PatientFormStatusUpdatedEvent::class);
    Event::assertNotDispatched(PatientFormCompletedEvent::class);
});

test('it fires the completed side-effects (review task) on transition to completed, without duplicating on a second run', function () {
    $form = makeGvlForm('103', FormStatus::Step3_completed, ['person_id' => Person::factory()]);
    $leadId = $form->anamnesis->lead_id;

    Http::fake(['http://forms/api/forms/103/status' => Http::response(['status' => 'completed'], 200)]);

    $this->artisan('forms:sync-anamnesis-status')->assertSuccessful();

    expect($form->fresh()->gvl_form_status)->toBe(FormStatus::Completed);

    $tasks = Activity::where('lead_id', $leadId)
        ->where('type', ActivityType::TASK->value)
        ->where('title', 'GVL controleren');

    expect($tasks->count())->toBe(1);

    // Second run: the form is now 'completed', so it is filtered out and not polled again.
    $this->artisan('forms:sync-anamnesis-status')->assertSuccessful();

    expect($tasks->count())->toBe(1);
});

test('it updates the status but skips the review task when the anamnesis has no person', function () {
    $form = makeGvlForm('104', FormStatus::Step2_completed, ['person_id' => null]);

    Http::fake(['http://forms/api/forms/104/status' => Http::response(['status' => 'completed'], 200)]);

    $this->artisan('forms:sync-anamnesis-status')->assertSuccessful();

    expect($form->fresh()->gvl_form_status)->toBe(FormStatus::Completed)
        ->and(Activity::where('title', 'GVL controleren')->exists())->toBeFalse();
});

test('it does not poll forms that are already completed', function () {
    makeGvlForm('105', FormStatus::Completed);

    Http::fake();

    $this->artisan('forms:sync-anamnesis-status')->assertSuccessful();

    Http::assertNothingSent();
});

test('a failing forms API call for one form does not block the others', function () {
    $ok = makeGvlForm('106', FormStatus::New);
    $broken = makeGvlForm('107', FormStatus::New);

    Http::fake([
        'http://forms/api/forms/106/status' => Http::response(['status' => 'step1'], 200),
        'http://forms/api/forms/107/status' => Http::response([], 500),
    ]);

    $this->artisan('forms:sync-anamnesis-status')->assertSuccessful();

    expect($ok->fresh()->gvl_form_status)->toBe(FormStatus::Step1_completed)
        ->and($broken->fresh()->gvl_form_status)->toBe(FormStatus::New);
});
