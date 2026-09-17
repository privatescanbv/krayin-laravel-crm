<?php

use App\Enums\FormStatus;
use App\Enums\FormType;
use App\Models\Anamnesis;
use App\Models\AnamnesisGvlForm;
use App\Models\Order;
use App\Models\SalesLead;
use App\Services\Afb\AfbDispatchService;
use App\Services\Anamnesis\AnamnesisGvlFormResolver;
use App\Services\Anamnesis\AnamnesisGvlFormReuseService;
use App\Services\FormService;
use Database\Seeders\TestSeeder;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Email\Enums\EmailFolderEnum;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Lead\Models\Lead;

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);
    $this->seed(TestSeeder::class);
    $this->actingAs(getDefaultAdmin(), 'user');

    config([
        'services.portal.patient.api_url'   => 'http://forms',
        'services.portal.patient.api_token' => 'test-token',
        'services.portal.patient.web_url'   => 'http://portal',
    ]);

    Http::fake([
        'http://forms/api/forms/*' => Http::response([], 200),
    ]);
});

/**
 * @return array{
 *     person: Person,
 *     sourceForm: AnamnesisGvlForm,
 *     sourceAnamnesis: Anamnesis,
 *     targetAnamnesis: Anamnesis,
 *     sourceOrder: Order,
 *     targetOrder: Order
 * }
 */
function makeGvlReuseOrders(bool $sameSales = false): array
{
    $person = Person::factory()->create();
    $leadA = Lead::factory()->create();
    $salesA = SalesLead::factory()->create(['lead_id' => $leadA->id]);
    $orderA = Order::factory()->create(['sales_lead_id' => $salesA->id, 'order_number' => 'ORD-A']);

    $sourceAnamnesis = Anamnesis::factory()->create([
        'order_id'  => $orderA->id,
        'lead_id'   => null,
        'sales_id'  => null,
        'person_id' => $person->id,
    ]);

    $sourceForm = AnamnesisGvlForm::create([
        'anamnesis_id'    => $sourceAnamnesis->id,
        'gvl_form_id'     => '9001',
        'gvl_form_status' => FormStatus::Completed,
        'gvl_form_type'   => FormType::PrivateScan,
        'completed_at'    => now()->subDays(5),
    ]);

    if ($sameSales) {
        $orderB = Order::factory()->create(['sales_lead_id' => $salesA->id, 'order_number' => 'ORD-B']);
        $targetAnamnesis = Anamnesis::factory()->create([
            'lead_id'   => $leadA->id,
            'sales_id'  => null,
            'order_id'  => null,
            'person_id' => $person->id,
        ]);
    } else {
        $leadB = Lead::factory()->create();
        $salesB = SalesLead::factory()->create(['lead_id' => $leadB->id]);
        $orderB = Order::factory()->create(['sales_lead_id' => $salesB->id, 'order_number' => 'ORD-B']);
        $targetAnamnesis = Anamnesis::factory()->create([
            'lead_id'   => $leadB->id,
            'sales_id'  => null,
            'order_id'  => null,
            'person_id' => $person->id,
        ]);
    }

    return [
        'person'          => $person,
        'sourceForm'      => $sourceForm,
        'sourceAnamnesis' => $sourceAnamnesis,
        'targetAnamnesis' => $targetAnamnesis,
        'sourceOrder'     => $orderA,
        'targetOrder'     => $orderB,
    ];
}

test('overnemen from another order on a different lead/sales chain links the same form id without Forms API create', function () {
    $ctx = makeGvlReuseOrders(sameSales: false);

    $this->postJson(route('admin.anamnesis.gvl-form.reuse', $ctx['targetAnamnesis']->id), [
        'source_gvl_form_record_id' => $ctx['sourceForm']->id,
    ])
        ->assertOk()
        ->assertJsonPath('message', 'GVL formulier is overgenomen.');

    Http::assertNotSent(fn ($request) => $request->method() === 'POST' && preg_match('#/api/forms/?$#', $request->url()));

    $cloned = AnamnesisGvlForm::query()
        ->where('anamnesis_id', $ctx['targetAnamnesis']->id)
        ->where('gvl_form_id', '9001')
        ->first();

    expect($cloned)->not->toBeNull()
        ->and($cloned->id)->not->toBe($ctx['sourceForm']->id)
        ->and($cloned->gvl_form_status)->toBe(FormStatus::Completed)
        ->and($cloned->gvl_form_type)->toBe(FormType::PrivateScan)
        ->and($cloned->completed_at?->equalTo($ctx['sourceForm']->completed_at))->toBeTrue();

    expect(AnamnesisGvlForm::where('gvl_form_id', '9001')->count())->toBe(2);
});

test('overnemen from a sibling order on the same sales chain links the same form id', function () {
    $ctx = makeGvlReuseOrders(sameSales: true);

    $this->postJson(route('admin.anamnesis.gvl-form.reuse', $ctx['targetAnamnesis']->id), [
        'source_gvl_form_record_id' => $ctx['sourceForm']->id,
    ])->assertOk();

    Http::assertNotSent(fn ($request) => $request->method() === 'POST' && preg_match('#/api/forms/?$#', $request->url()));

    expect(AnamnesisGvlForm::query()
        ->where('anamnesis_id', $ctx['targetAnamnesis']->id)
        ->where('gvl_form_id', '9001')
        ->exists())->toBeTrue();
});

test('overnemen of an incomplete form returns 422', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $sourceAnamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id, 'person_id' => $person->id]);
    $targetAnamnesis = Anamnesis::factory()->create(['lead_id' => Lead::factory()->create()->id, 'person_id' => $person->id]);

    $sourceForm = AnamnesisGvlForm::create([
        'anamnesis_id'    => $sourceAnamnesis->id,
        'gvl_form_id'     => '9002',
        'gvl_form_status' => FormStatus::Step1_completed,
        'gvl_form_type'   => FormType::PrivateScan,
    ]);

    $this->postJson(route('admin.anamnesis.gvl-form.reuse', $targetAnamnesis->id), [
        'source_gvl_form_record_id' => $sourceForm->id,
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Alleen een voltooide GVL kan worden overgenomen.');
});

test('overnemen of a form belonging to another person returns 422', function () {
    $ctx = makeGvlReuseOrders();
    $otherPerson = Person::factory()->create();
    $otherAnamnesis = Anamnesis::factory()->create([
        'lead_id'   => Lead::factory()->create()->id,
        'person_id' => $otherPerson->id,
    ]);

    $this->postJson(route('admin.anamnesis.gvl-form.reuse', $otherAnamnesis->id), [
        'source_gvl_form_record_id' => $ctx['sourceForm']->id,
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Formulier hoort bij een andere persoon.');
});

test('overnemen writes an overgenomen audit activity', function () {
    $ctx = makeGvlReuseOrders();

    $this->postJson(route('admin.anamnesis.gvl-form.reuse', $ctx['targetAnamnesis']->id), [
        'source_gvl_form_record_id' => $ctx['sourceForm']->id,
    ])->assertOk();

    $activity = Activity::query()
        ->whereNotNull('additional->gvl_form_id')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->title)->toContain('overgenomen')
        ->and($activity->additional['gvl_form_id'])->toBe('9001');
});

test('candidates list completed GVL from another order and hide it after reuse', function () {
    $ctx = makeGvlReuseOrders();
    $service = app(AnamnesisGvlFormReuseService::class);

    $candidates = $service->candidatesForAnamnesis($ctx['targetAnamnesis']);

    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]['id'])->toBe($ctx['sourceForm']->id)
        ->and($candidates[0]['needs_age_warning'])->toBeFalse()
        ->and($candidates[0]['source_label'])->toContain('ORD-A');

    $service->reuseOnto($ctx['targetAnamnesis'], $ctx['sourceForm']->id);

    expect($service->candidatesForAnamnesis($ctx['targetAnamnesis']->fresh()))->toBeEmpty();
});

test('candidates warn when the completed GVL is older than 30 days', function () {
    $ctx = makeGvlReuseOrders();
    $ctx['sourceForm']->update(['completed_at' => now()->subDays(45)]);

    $candidates = app(AnamnesisGvlFormReuseService::class)->candidatesForAnamnesis($ctx['targetAnamnesis']);

    expect($candidates[0]['needs_age_warning'])->toBeTrue()
        ->and($candidates[0]['age_warning'])->toContain('45 dagen oud');
});

test('after overnemen AFB lookup finds the completed GVL on the new order and attaches the PDF', function () {
    $ctx = makeGvlReuseOrders();

    $this->postJson(route('admin.anamnesis.gvl-form.reuse', $ctx['targetAnamnesis']->id), [
        'source_gvl_form_record_id' => $ctx['sourceForm']->id,
    ])->assertOk();

    $resolver = app(AnamnesisGvlFormResolver::class);
    $records = $resolver->loadForOrder($ctx['targetOrder']->fresh(['salesLead']));
    $anamnesis = $resolver->resolveForPerson($records, $ctx['targetOrder']->id, $ctx['person']->id);
    $forms = $resolver->completedFormsForAnamnesis($anamnesis->load('gvlForms'));

    expect($forms)->toHaveCount(1)
        ->and($forms->first()->gvl_form_id)->toBe('9001');

    $folder = Folder::create(['name' => EmailFolderEnum::INBOX->value]);
    $email = Email::create([
        'subject'   => 'AFB test',
        'from'      => ['clinic@example.com'],
        'reply'     => 'body',
        'folder_id' => $folder->id,
    ]);

    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('successful')->andReturn(true);
    $mockResponse->shouldReceive('body')->andReturn('%PDF-fake');

    $this->mock(FormService::class, function ($mock) use ($mockResponse) {
        $mock->shouldReceive('downloadForm')->andReturn($mockResponse);
    });

    app(AfbDispatchService::class)->attachGvlPdfsToEmail(
        $email,
        [[
            'order'        => $ctx['targetOrder']->fresh(['salesLead']),
            'person_id'    => $ctx['person']->id,
            'patient_name' => $ctx['person']->name,
        ]],
        $ctx['targetOrder']->fresh(['salesLead']),
    );

    $email->refresh()->load('attachments');

    expect($email->attachments)->toHaveCount(1)
        ->and($email->attachments->first()->name)->toStartWith('gvl-');
});

test('detaching a reused GVL does not delete the portal form while the original still exists', function () {
    $ctx = makeGvlReuseOrders();

    $this->postJson(route('admin.anamnesis.gvl-form.reuse', $ctx['targetAnamnesis']->id), [
        'source_gvl_form_record_id' => $ctx['sourceForm']->id,
    ])->assertOk();

    Http::fake();

    $cloned = AnamnesisGvlForm::query()
        ->where('anamnesis_id', $ctx['targetAnamnesis']->id)
        ->where('gvl_form_id', '9001')
        ->firstOrFail();

    $this->deleteJson(route('admin.anamnesis.gvl-form.detach', [
        'id'               => $ctx['targetAnamnesis']->id,
        'gvlFormRecordId'  => $cloned->id,
    ]))->assertOk();

    Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');

    expect(AnamnesisGvlForm::find($cloned->id))->toBeNull()
        ->and(AnamnesisGvlForm::find($ctx['sourceForm']->id))->not->toBeNull();
});

test('detaching the last CRM link deletes the portal form', function () {
    $ctx = makeGvlReuseOrders();

    Http::fake([
        'http://forms/api/forms/*' => Http::response([], 200),
    ]);

    $this->deleteJson(route('admin.anamnesis.gvl-form.detach', [
        'id'              => $ctx['sourceAnamnesis']->id,
        'gvlFormRecordId' => $ctx['sourceForm']->id,
    ]))->assertOk();

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), '/api/forms/9001'));

    expect(AnamnesisGvlForm::find($ctx['sourceForm']->id))->toBeNull();
});

test('reverting an order override moves a completed GVL to sales instead of deleting it', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $sales = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create(['sales_lead_id' => $sales->id]);

    $salesAnamnesis = Anamnesis::factory()->create([
        'sales_id'  => $sales->id,
        'lead_id'   => null,
        'order_id'  => null,
        'person_id' => $person->id,
    ]);
    $orderAnamnesis = Anamnesis::factory()->create([
        'order_id'  => $order->id,
        'lead_id'   => null,
        'sales_id'  => null,
        'person_id' => $person->id,
    ]);

    $form = AnamnesisGvlForm::create([
        'anamnesis_id'    => $orderAnamnesis->id,
        'gvl_form_id'     => '9001',
        'gvl_form_status' => FormStatus::Completed,
        'gvl_form_type'   => FormType::PrivateScan,
        'completed_at'    => now(),
    ]);

    Http::fake();

    $this->delete(route('admin.anamnesis.revert-override'), [
        'order_id'   => $order->id,
        'person_id'  => $person->id,
        'return_url' => '/admin/orders/view/'.$order->id,
    ])->assertRedirect();

    Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');

    expect(Anamnesis::find($orderAnamnesis->id))->toBeNull()
        ->and($form->fresh()->anamnesis_id)->toBe($salesAnamnesis->id)
        ->and($form->fresh()->gvl_form_status)->toBe(FormStatus::Completed);
});

test('reverting an order override still detaches incomplete GVL forms', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $sales = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create(['sales_lead_id' => $sales->id]);

    Anamnesis::factory()->create([
        'sales_id'  => $sales->id,
        'lead_id'   => null,
        'person_id' => $person->id,
    ]);
    $orderAnamnesis = Anamnesis::factory()->create([
        'order_id'  => $order->id,
        'lead_id'   => null,
        'sales_id'  => null,
        'person_id' => $person->id,
    ]);

    $form = AnamnesisGvlForm::create([
        'anamnesis_id'    => $orderAnamnesis->id,
        'gvl_form_id'     => '9001',
        'gvl_form_status' => FormStatus::Step2_completed,
        'gvl_form_type'   => FormType::PrivateScan,
    ]);

    $this->delete(route('admin.anamnesis.revert-override'), [
        'order_id'   => $order->id,
        'person_id'  => $person->id,
        'return_url' => '/admin/orders/view/'.$order->id,
    ])->assertRedirect();

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), '/api/forms/9001'));

    expect(AnamnesisGvlForm::find($form->id))->toBeNull();
});
