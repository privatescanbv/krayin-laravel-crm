<?php

namespace Tests\Feature\Anamnesis;

use App\Enums\ActivityType;
use App\Enums\FormType;
use App\Events\PatientFormCompletedEvent;
use App\Models\Anamnesis;
use App\Models\AnamnesisGvlForm;
use App\Models\Order;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Lead\Models\Lead;

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);
    $this->seed(TestSeeder::class);

    config([
        'services.portal.patient.api_url'   => 'http://forms',
        'services.portal.patient.api_token' => 'test-token',
    ]);

    Http::fake([
        'http://forms/api/forms'    => Http::response(['data' => ['id' => 500], 'form_url' => 'https://forms.example.com/forms/500/step/1'], 201),
        'http://forms/api/forms/*'  => Http::response([], 200),
    ]);
});

/** @return Activity|null */
function lastGvlAuditActivity()
{
    return Activity::where('type', ActivityType::SYSTEM)
        ->whereNotNull('additional->gvl_form_record_id')
        ->latest('id')
        ->first();
}

test('creating a GVL form as an authenticated admin logs a system activity on the resolved entity', function () {
    $admin = getDefaultAdmin();
    $this->actingAs($admin, 'user');

    $lead = Lead::factory()->create();
    $person = Person::factory()->create();
    $lead->attachPersons([$person->id]);
    $anamnesis = Anamnesis::where('lead_id', $lead->id)->where('person_id', $person->id)->firstOrFail();

    $this->postJson(route('admin.anamnesis.gvl-form.attach', ['id' => $anamnesis->id]))->assertOk();

    $activity = lastGvlAuditActivity();

    expect($activity)->not->toBeNull()
        ->and($activity->user_id)->toBe($admin->id)
        ->and($activity->lead_id)->toBe($lead->id)
        ->and($activity->order_id)->toBeNull()
        ->and($activity->title)->toContain('aangemaakt')
        ->and($activity->title)->toContain('GVL')
        ->and($activity->additional['anamnesis_id'])->toBe($anamnesis->id);
});

test('creating a GVL form on an order-level anamnesis logs the activity on the order', function () {
    $this->actingAs(getDefaultAdmin(), 'user');

    $person = Person::factory()->create();
    $order = Order::factory()->create();
    $anamnesis = Anamnesis::factory()->create([
        'order_id'  => $order->id,
        'person_id' => $person->id,
        'lead_id'   => null,
        'sales_id'  => null,
    ]);

    $this->postJson(route('admin.anamnesis.gvl-form.attach', ['id' => $anamnesis->id]))->assertOk();

    $activity = lastGvlAuditActivity();

    expect($activity)->not->toBeNull()
        ->and($activity->order_id)->toBe($order->id)
        ->and($activity->lead_id)->toBeNull()
        ->and($activity->title)->toContain('aangemaakt');
});

test('detaching a GVL form logs a "verwijderd" system activity', function () {
    $this->actingAs(getDefaultAdmin(), 'user');

    $lead = Lead::factory()->create();
    $person = Person::factory()->create();
    $lead->attachPersons([$person->id]);
    $anamnesis = Anamnesis::where('lead_id', $lead->id)->where('person_id', $person->id)->firstOrFail();

    $gvlForm = AnamnesisGvlForm::create(['anamnesis_id' => $anamnesis->id, 'gvl_form_id' => '123']);

    $this->deleteJson(route('admin.anamnesis.gvl-form.detach', ['id' => $anamnesis->id, 'gvlFormRecordId' => $gvlForm->id]))
        ->assertOk();

    expect(lastGvlAuditActivity()?->title)->toContain('verwijderd');
});

test('completing a GVL form via the forms webhook event logs an "afgerond" system activity', function () {
    $lead = Lead::factory()->create();
    $person = Person::factory()->create();
    $lead->attachPersons([$person->id]);
    $anamnesis = Anamnesis::where('lead_id', $lead->id)->where('person_id', $person->id)->firstOrFail();

    AnamnesisGvlForm::create(['anamnesis_id' => $anamnesis->id, 'gvl_form_id' => '77']);
    Activity::query()->delete(); // drop the "aangemaakt" entry so we assert on the completion one

    PatientFormCompletedEvent::dispatch($person, '77', FormType::PrivateScan);

    $activity = lastGvlAuditActivity();

    expect($activity)->not->toBeNull()
        ->and($activity->title)->toContain('afgerond')
        ->and($activity->lead_id)->toBe($lead->id);
});

test('a form-completed event for an unknown form id logs nothing', function () {
    $person = Person::factory()->create();

    PatientFormCompletedEvent::dispatch($person, 'does-not-exist', FormType::PrivateScan);

    expect(lastGvlAuditActivity())->toBeNull();
});

test('creating a GVL form without an authenticated user records a null user_id', function () {
    Auth::guard('user')->logout();

    $lead = Lead::factory()->create();
    $person = Person::factory()->create();
    $lead->attachPersons([$person->id]);
    $anamnesis = Anamnesis::where('lead_id', $lead->id)->where('person_id', $person->id)->firstOrFail();

    AnamnesisGvlForm::create(['anamnesis_id' => $anamnesis->id, 'gvl_form_id' => '999']);

    $activity = lastGvlAuditActivity();

    expect($activity)->not->toBeNull()
        ->and($activity->user_id)->toBeNull()
        ->and($activity->title)->toContain('aangemaakt');
});
