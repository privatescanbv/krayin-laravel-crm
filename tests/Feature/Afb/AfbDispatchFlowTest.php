<?php

use App\Enums\AfbDispatchType;
use App\Enums\PersonSalutation;
use App\Enums\PipelineType;
use App\Jobs\SendAfbDispatchJob;
use App\Models\Address;
use App\Models\AfbPersonDocument;
use App\Models\Anamnesis;
use App\Models\Clinic;
use App\Models\ClinicDepartment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use App\Models\SalesLead;
use App\Services\Afb\AfbDispatchService;
use App\Services\Afb\AfbDocumentGenerator;
use Carbon\Carbon;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Webkul\Contact\Models\Person;
use Webkul\Email\Mails\Email as EmailMailable;
use Webkul\Email\Models\Email;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Lead\Models\Stage;
use Webkul\Product\Models\Product;

uses(RefreshDatabase::class);

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);

    $this->seed(TestSeeder::class);
    $this->actingAs(makeUser(), 'user');
});

/**
 * @return array{
 *     clinic: Clinic,
 *     department: ClinicDepartment,
 *     order: Order,
 *     person: Person
 * }
 */
function createOrderForClinic(Carbon $examAt, ?Clinic $clinic = null): array
{
    $clinic = $clinic ?: Clinic::factory()->create([
        'name'                         => 'Evidia',
        'registration_form_clinic_name'=> 'Evidia - Augusta Klinik',
        'emails'                       => [['value' => 'clinic@example.com', 'is_default' => true]],
    ]);

    $department = ClinicDepartment::factory()->create([
        'clinic_id' => $clinic->id,
        'name'      => 'Radiologie',
        'email'     => 'dept@example.com',
    ]);

    $address = Address::factory()->create([
        'street'              => 'Haagweg',
        'house_number'        => '89',
        'house_number_suffix' => 'D',
        'postal_code'         => '2321AA',
        'city'                => 'LEIDEN',
        'country'             => 'Nederland',
    ]);

    $person = Person::factory()->create([
        'salutation'          => PersonSalutation::Mevr->value,
        'first_name'          => 'Lara',
        'last_name'           => 'Muller',
        'married_name_prefix' => 'de',
        'married_name'        => 'Boer',
        'address_id'          => $address->id,
        'is_active'           => true,
    ]);

    $salesLead = SalesLead::factory()->create([
        'contact_person_id' => $person->id,
        'user_id'           => auth()->id(),
    ]);
    $salesLead->attachPersons([$person->id]);

    $order = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'user_id'              => auth()->id(),
        'order_number'         => 'ORD-TEST-1001',
        'first_examination_at' => $examAt->copy(),
    ]);

    $product = Product::factory()->create([
        'name' => 'MRT HWS',
    ]);

    $partnerProduct = PartnerProduct::factory()->create([
        'product_id'         => $product->id,
        'clinic_description' => 'MRT HWS zonder KM',
    ]);
    $partnerProduct->clinics()->sync([$clinic->id]);

    $orderItem = OrderItem::factory()->create([
        'order_id'    => $order->id,
        'product_id'  => $product->id,
        'person_id'   => $person->id,
        'name'        => 'MRT HWS',
        'description' => 'MRT HWS zonder KM',
    ]);

    $resource = Resource::factory()->create([
        'clinic_id'            => $clinic->id,
        'clinic_department_id' => $department->id,
    ]);

    ResourceOrderItem::factory()->create([
        'resource_id'  => $resource->id,
        'orderitem_id' => $orderItem->id,
        'from'         => $examAt->copy()->addMinutes(30),
        'to'           => $examAt->copy()->addMinutes(90),
    ]);

    // attachPersons() already created a skeleton anamnesis via firstOrCreate; update it with test values.
    Anamnesis::where('sales_id', $salesLead->id)
        ->where('person_id', $person->id)
        ->update([
            'lead_id'        => $salesLead->lead_id,
            'claustrophobia' => true,
            'diabetes'       => false,
            'metals'         => true,
            'metals_notes'   => 'Schroef in knie',
            'heart_surgery'  => false,
            'implant'        => true,
            'implant_notes'  => 'Heupprothese links',
            'allergies'      => false,
            'glaucoma'       => false,
            'remarks'        => 'Nuchter',
            'comment_clinic' => 'Nekpijn links, voorzichtig positioneren.',
        ]);

    return ['clinic' => $clinic, 'department' => $department, 'order' => $order, 'person' => $person];
}

test('afb generator maps crm fields into afb layout', function () {
    $context = createOrderForClinic(Carbon::parse('2026-03-31 09:30:00'));

    $generator = app(AfbDocumentGenerator::class);
    $rendered = $generator->renderHtmlForOrderAndClinic($context['order']->fresh(), $context['clinic']);
    $html = $rendered['html'];

    expect($html)
        ->toContain('Anforderungsformular Behandlung')
        ->toContain('Evidia - Augusta Klinik')
        ->toContain('ORD-TEST-1001')
        ->toContain('Lara')
        ->toContain('Muller - de Boer')
        ->toContain('MRT HWS zonder KM')
        ->toContain('Ja')
        ->toContain('Nein')
        ->toContain('Schroef in knie')
        ->toContain('Nekpijn links, voorzichtig positioneren.')
        ->not->toContain('Kode')
        ->not->toContain('Verk.nr');
});

test('daily afb command queues one batch job per department', function () {
    Bus::fake();

    $targetDate = now()->addDays(3)->setTime(10, 0);
    $context = createOrderForClinic($targetDate);

    $this->artisan('afb:send-daily --date='.$targetDate->toDateString())
        ->assertExitCode(0);

    Bus::assertDispatched(SendAfbDispatchJob::class, function (SendAfbDispatchJob $job) use ($context) {
        return $job->departmentId === $context['department']->id
            && $job->type === AfbDispatchType::BATCH->value
            && $job->orderIds === [$context['order']->id];
    });
});

test('late booking planning queues individual afb dispatch', function () {
    Bus::fake();

    $examAt = now()->addHours(8);
    $context = createOrderForClinic($examAt);

    Bus::assertDispatched(SendAfbDispatchJob::class, function (SendAfbDispatchJob $job) use ($context) {
        return $job->departmentId === $context['department']->id
            && $job->type === AfbDispatchType::INDIVIDUAL->value
            && $job->orderIds === [$context['order']->id];
    });
});

test('dispatch service prevents duplicate send to same department', function () {
    Mail::fake();

    $context = createOrderForClinic(now()->addDays(2)->setTime(11, 0));
    $service = app(AfbDispatchService::class);

    $service->sendDispatch(
        departmentId: $context['department']->id,
        orderIds: [$context['order']->id],
        type: AfbDispatchType::INDIVIDUAL,
        attempt: 1
    );

    $service->sendDispatch(
        departmentId: $context['department']->id,
        orderIds: [$context['order']->id],
        type: AfbDispatchType::INDIVIDUAL,
        attempt: 1
    );

    expect(AfbPersonDocument::query()
        ->whereHas('dispatch', fn ($q) => $q->where('clinic_department_id', $context['department']->id))
        ->where('order_id', $context['order']->id)
        ->count())->toBe(1)
        ->and(Email::query()->where('clinic_id', $context['clinic']->id)->count())->toBe(1);

    expect(AfbPersonDocument::query()
        ->where('order_id', $context['order']->id)
        ->whereHas('dispatch', fn ($q) => $q->where('clinic_department_id', $context['department']->id))
        ->exists())->toBeTrue();

    Mail::assertSent(EmailMailable::class, 1);
});

test('isAlreadySentToDepartment uses dispatch rows not order snapshot after second clinic', function () {
    Mail::fake();

    $examAt = now()->addDays(2)->setTime(11, 0);
    $context = createOrderForClinic($examAt);

    $clinicB = Clinic::factory()->create([
        'name'                          => 'Kliniek B',
        'registration_form_clinic_name' => 'Kliniek B',
        'emails'                        => [['value' => 'b@example.com', 'is_default' => true]],
    ]);
    $departmentB = ClinicDepartment::factory()->create([
        'clinic_id' => $clinicB->id,
        'email'     => 'deptb@example.com',
    ]);
    $resourceB = Resource::factory()->create([
        'clinic_id'            => $clinicB->id,
        'clinic_department_id' => $departmentB->id,
    ]);
    $orderItem = $context['order']->orderItems()->first();
    $partnerProduct = PartnerProduct::where('product_id', $orderItem->product_id)->first();
    $partnerProduct->clinics()->syncWithoutDetaching([$clinicB->id]);

    ResourceOrderItem::factory()->create([
        'resource_id'  => $resourceB->id,
        'orderitem_id' => $orderItem->id,
        'from'         => $examAt->copy()->addDay(),
        'to'           => $examAt->copy()->addDay()->addMinutes(60),
    ]);

    $service = app(AfbDispatchService::class);

    $service->sendDispatch(
        departmentId: $context['department']->id,
        orderIds: [$context['order']->id],
        type: AfbDispatchType::INDIVIDUAL,
        attempt: 1
    );

    $service->sendDispatch(
        departmentId: $departmentB->id,
        orderIds: [$context['order']->id],
        type: AfbDispatchType::INDIVIDUAL,
        attempt: 1
    );

    expect($service->isAlreadySentToDepartment($context['order']->id, $context['department']->id))->toBeTrue()
        ->and($service->isAlreadySentToDepartment($context['order']->id, $departmentB->id))->toBeTrue()
        ->and(
            AfbPersonDocument::query()
                ->where('order_id', $context['order']->id)
                ->whereHas('dispatch', fn ($q) => $q->where('clinic_department_id', $departmentB->id))
                ->exists()
        )->toBeTrue();
});

test('daily batch does not dispatch for department whose exam is later that week', function () {
    Bus::fake();

    $targetDate = now()->addDays(3)->setTime(10, 0);
    $laterDate = now()->addDays(7)->setTime(10, 0);

    $context = createOrderForClinic($targetDate);

    $clinicB = Clinic::factory()->create();
    $departmentB = ClinicDepartment::factory()->create([
        'clinic_id' => $clinicB->id,
        'email'     => 'deptb@example.com',
    ]);
    $resource = Resource::factory()->create([
        'clinic_id'            => $clinicB->id,
        'clinic_department_id' => $departmentB->id,
    ]);
    $orderItem = $context['order']->orderItems()->first();

    ResourceOrderItem::factory()->create([
        'resource_id'  => $resource->id,
        'orderitem_id' => $orderItem->id,
        'from'         => $laterDate,
        'to'           => $laterDate->copy()->addMinutes(60),
    ]);

    $this->artisan('afb:send-daily --date='.$targetDate->toDateString())
        ->assertExitCode(0);

    Bus::assertDispatched(SendAfbDispatchJob::class, fn (SendAfbDispatchJob $job) => $job->departmentId === $context['department']->id);
    Bus::assertNotDispatched(SendAfbDispatchJob::class, fn (SendAfbDispatchJob $job) => $job->departmentId === $departmentB->id);
});

test('adding next week department to order with imminent exam does not trigger immediate dispatch', function () {
    Bus::fake();

    $examSoon = now()->addHours(8);
    $examLater = now()->addDays(7)->setTime(10, 0);

    $context = createOrderForClinic($examSoon);

    $clinicB = Clinic::factory()->create();
    $departmentB = ClinicDepartment::factory()->create([
        'clinic_id' => $clinicB->id,
        'email'     => 'deptb@example.com',
    ]);
    $resource = Resource::factory()->create([
        'clinic_id'            => $clinicB->id,
        'clinic_department_id' => $departmentB->id,
    ]);
    $orderItem = $context['order']->orderItems()->first();

    ResourceOrderItem::factory()->create([
        'resource_id'  => $resource->id,
        'orderitem_id' => $orderItem->id,
        'from'         => $examLater,
        'to'           => $examLater->copy()->addMinutes(60),
    ]);

    Bus::assertNotDispatched(SendAfbDispatchJob::class, fn (SendAfbDispatchJob $job) => $job->departmentId === $departmentB->id);
});

test('clinic view contains afb verzendingen navigation', function () {
    $clinic = Clinic::factory()->create();

    $response = $this->get(route('admin.clinics.view', $clinic->id));

    $response->assertOk();
    $response->assertSee('AFB verzendingen');
});

test('order view shows manual afb banner when late booking and not yet sent', function () {
    // ResourceOrderItemObserver queues late AFB when planning binnen 24u; zonder fake draait de job sync
    // en is de order al "verstuurd", waardoor de banner niet verschijnt.
    Bus::fake();

    // Banner is alleen zichtbaar met orders.edit; makeUser() uit beforeEach heeft die permissie niet.
    $this->actingAs(getDefaultAdmin(), 'user');

    $examAt = now()->addHours(10);
    $context = createOrderForClinic($examAt);

    $stage = Stage::query()->whereHas('pipeline', fn ($q) => $q->where('type', PipelineType::ORDER))->firstOrFail();
    $context['order']->update(['pipeline_stage_id' => $stage->id]);

    expect(app(AfbDispatchService::class)->needsManualLateAfb($context['order']->fresh()))->toBeTrue();

    $response = $this->get(route('admin.orders.view', $context['order']->id));

    $response->assertOk();
    $response->assertSee('AFB: handmatige verzending nodig', false);
    $response->assertSee('AFB nu versturen', false);
});
