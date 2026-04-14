<?php

use App\Enums\AfbDispatchStatus;
use App\Enums\AfbDispatchType;
use App\Enums\OrderItemStatus;
use App\Enums\PersonSalutation;
use App\Enums\PipelineStage;
use App\Enums\ResourceType as ResourceTypeEnum;
use App\Jobs\SendAfbDispatchJob;
use App\Models\Address;
use App\Models\AfbDispatch;
use App\Models\AfbPersonDocument;
use App\Models\Anamnesis;
use App\Models\Clinic;
use App\Models\ClinicDepartment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use App\Models\ResourceType;
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
        'pipeline_stage_id'    => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
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
    $rendered = $generator->renderHtmlForOrderAndDepartment($context['order']->fresh(), $context['department']);
    $html = $rendered['html'];

    expect($html)
        ->toContain('Anforderungsbogen Privatescan')
        ->toContain('Evidia - Augusta Klinik')
        ->toContain('ORD-TEST-1001')
        ->toContain('Lara')
        ->toContain('de Boer - Muller')
        ->toContain('MRT HWS zonder KM')
        ->toContain('Ja')
        ->toContain('Nein')
        ->toContain('Schroef in knie')
        ->toContain('Nekpijn links, voorzichtig positioneren.')
        ->not->toContain('Kode');

    // Summary row: Start = earliest resource_orderitem.from (09:30 + 30m = 10:00), not first_examination_at time.
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

test('getUniqueDepartmentIdsForOrder includes department for plannable order items', function () {
    $context = createOrderForClinic(now()->addDays(2)->setTime(10, 0));

    $ids = app(AfbDispatchService::class)->getUniqueDepartmentIdsForOrder($context['order']->id);

    expect($ids)->toContain($context['department']->id);
});

test('getUniqueDepartmentIdsForOrder returns empty when partner product is not plannable', function () {
    $context = createOrderForClinic(now()->addDays(2)->setTime(10, 0));

    $otherTypeId = ResourceType::query()
        ->where('name', ResourceTypeEnum::OTHER->label())
        ->value('id');

    PartnerProduct::where('product_id', $context['order']->orderItems()->first()->product_id)
        ->update(['resource_type_id' => $otherTypeId]);

    $ids = app(AfbDispatchService::class)->getUniqueDepartmentIdsForOrder($context['order']->id);

    expect($ids)->toBe([]);
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
        ->and(Email::query()->where('clinic_id', $context['clinic']->id)->count())->toBe(1)
        ->and(AfbPersonDocument::query()
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

    expect(app(AfbDispatchService::class)->getAvbDispatchReadiness($context['order']->fresh())['needs_manual_send'])->toBeTrue();

    $response = $this->get(route('admin.orders.view', $context['order']->id));

    $response->assertOk();
    $response->assertSee('AFB: handmatige verzending nodig', false);
    $response->assertSee('AFB nu versturen', false);
});

test('banner disappears after sending afb that includes new order item', function () {
    Bus::fake();
    Mail::fake();

    $this->actingAs(getDefaultAdmin(), 'user');

    $examAt = now()->addHours(10);
    $context = createOrderForClinic($examAt);

    $service = app(AfbDispatchService::class);

    // Haal de bestaande orderregel IDs op (die wél in de vorige AFB zaten)
    $existingItemIds = $context['order']->orderItems()->pluck('id')->toArray();

    // Simuleer een succesvolle dispatch van 5 minuten geleden (via factory, geen PDF nodig)
    $dispatch = AfbDispatch::factory()->create([
        'clinic_department_id' => $context['department']->id,
        'clinic_id'            => $context['clinic']->id,
        'status'               => AfbDispatchStatus::SUCCESS->value,
        'sent_at'              => now()->subMinutes(5),
    ]);
    AfbPersonDocument::factory()->create([
        'afb_dispatch_id' => $dispatch->id,
        'order_id'        => $context['order']->id,
        'order_item_ids'  => $existingItemIds,
    ]);

    // Nieuwe orderregel toegevoegd — ID staat niet in de vorige AFB
    $newItem = OrderItem::factory()->create([
        'order_id' => $context['order']->id,
    ]);
    $deptResource = Resource::where('clinic_department_id', $context['department']->id)->firstOrFail();
    ResourceOrderItem::factory()->create([
        'resource_id'  => $deptResource->id,
        'orderitem_id' => $newItem->id,
        'from'         => $examAt->copy()->addMinutes(120),
        'to'           => $examAt->copy()->addMinutes(180),
    ]);

    // Banner moet zichtbaar zijn
    expect($service->getAvbDispatchReadiness($context['order']->fresh())['needs_manual_send'])->toBeTrue();

    // Verstuur opnieuw — sent_at wordt now() wat na created_at ligt
    $service->sendDispatch(
        departmentId: $context['department']->id,
        orderIds: [$context['order']->id],
        type: AfbDispatchType::INDIVIDUAL,
        attempt: 1
    );

    // Na de tweede dispatch moet de banner verdwijnen
    expect($service->getAvbDispatchReadiness($context['order']->fresh())['needs_manual_send'])->toBeFalse();
});

test('order view shows manual afb banner again after new order item added post dispatch', function () {
    Bus::fake();

    $this->actingAs(getDefaultAdmin(), 'user');

    $examAt = now()->addHours(10);
    $context = createOrderForClinic($examAt);

    // Haal de bestaande orderregel IDs op (die wél in de vorige AFB zaten)
    $existingItemIds = $context['order']->orderItems()->pluck('id')->toArray();

    // Simuleer een succesvolle dispatch die eerder plaatsvond
    $dispatch = AfbDispatch::factory()->create([
        'clinic_department_id' => $context['department']->id,
        'clinic_id'            => $context['clinic']->id,
        'status'               => AfbDispatchStatus::SUCCESS->value,
        'sent_at'              => now()->subMinutes(30),
    ]);
    AfbPersonDocument::factory()->create([
        'afb_dispatch_id' => $dispatch->id,
        'order_id'        => $context['order']->id,
        'order_item_ids'  => $existingItemIds,
    ]);

    // Voeg nu een nieuwe orderregel toe — ID staat niet in de vorige AFB
    $newItem = OrderItem::factory()->create([
        'order_id' => $context['order']->id,
    ]);
    $deptResource = Resource::where('clinic_department_id', $context['department']->id)->firstOrFail();
    ResourceOrderItem::factory()->create([
        'resource_id'  => $deptResource->id,
        'orderitem_id' => $newItem->id,
        'from'         => $examAt->copy()->addMinutes(120),
        'to'           => $examAt->copy()->addMinutes(180),
    ]);

    $response = $this->get(route('admin.orders.view', $context['order']->id));

    $response->assertOk();
    $response->assertSee('AFB: handmatige verzending nodig', false);
    $response->assertSee('AFB nu versturen', false);
});

test('banner does not show for other departments when new item only affects one department', function () {
    Bus::fake();
    Mail::fake();
    $this->actingAs(getDefaultAdmin(), 'user');

    $examAt = now()->addHours(10);
    $context = createOrderForClinic($examAt);
    $service = app(AfbDispatchService::class);

    // Tweede afdeling + resource voor dezelfde order
    $dept2 = ClinicDepartment::factory()->create(['clinic_id' => $context['clinic']->id, 'email' => 'dept2@example.com']);
    $resource2 = Resource::factory()->create(['clinic_id' => $context['clinic']->id, 'clinic_department_id' => $dept2->id]);
    $orderItem2 = $context['order']->orderItems()->first();
    ResourceOrderItem::factory()->create([
        'resource_id'  => $resource2->id,
        'orderitem_id' => $orderItem2->id,
        'from'         => $examAt->copy()->addMinutes(30),
        'to'           => $examAt->copy()->addMinutes(90),
    ]);

    // Beide afdelingen zijn al eerder succesvol verstuurd
    $existingItemIds = $context['order']->orderItems()->pluck('id')->toArray();

    $dispatch1 = AfbDispatch::factory()->create([
        'clinic_department_id' => $context['department']->id,
        'clinic_id'            => $context['clinic']->id,
        'status'               => AfbDispatchStatus::SUCCESS->value,
        'sent_at'              => now()->subMinutes(10),
    ]);
    AfbPersonDocument::factory()->create([
        'afb_dispatch_id' => $dispatch1->id,
        'order_id'        => $context['order']->id,
        'order_item_ids'  => $existingItemIds,
    ]);

    $dispatch2 = AfbDispatch::factory()->create([
        'clinic_department_id' => $dept2->id,
        'clinic_id'            => $context['clinic']->id,
        'status'               => AfbDispatchStatus::SUCCESS->value,
        'sent_at'              => now()->subMinutes(5),
    ]);
    AfbPersonDocument::factory()->create([
        'afb_dispatch_id' => $dispatch2->id,
        'order_id'        => $context['order']->id,
        'order_item_ids'  => $existingItemIds,
    ]);

    // Nieuw item toegevoegd — alleen voor afdeling 2
    $newItem = OrderItem::factory()->create(['order_id' => $context['order']->id]);
    ResourceOrderItem::factory()->create([
        'resource_id'  => $resource2->id,
        'orderitem_id' => $newItem->id,
        'from'         => $examAt->copy()->addMinutes(100),
        'to'           => $examAt->copy()->addMinutes(160),
    ]);

    // Banner toont (D2 heeft nieuwe item)
    expect($service->getAvbDispatchReadiness($context['order']->fresh())['needs_manual_send'])->toBeTrue();

    // D1 heeft geen onopgenomen items — het nieuwe item hoort bij D2
    expect($service->hasUnincludedActiveItems($context['order']->id, $context['department']->id))->toBeFalse();

    // Verstuur D2 opnieuw
    $service->sendDispatch($dept2->id, [$context['order']->id], AfbDispatchType::INDIVIDUAL, 1);

    // Na de tweede dispatch moet de banner verdwenen zijn voor de hele order
    expect($service->getAvbDispatchReadiness($context['order']->fresh())['needs_manual_send'])->toBeFalse();
});

// ---------------------------------------------------------------------------
// getAvbDispatchReadiness
// ---------------------------------------------------------------------------

test('getAvbDispatchReadiness returns not_ready when no first_examination_at', function () {
    $service = app(AfbDispatchService::class);

    $order = Order::factory()->create([
        'sales_lead_id'        => SalesLead::factory()->create(['user_id' => auth()->id()])->id,
        'user_id'              => auth()->id(),
        'first_examination_at' => null,
    ]);

    $readiness = $service->getAvbDispatchReadiness($order);

    expect($readiness['is_ready'])->toBeFalse()
        ->and($readiness['reasons'])->toContain('Geen eerste onderzoekdatum ingesteld');
});

test('getAvbDispatchReadiness returns not_ready when examination date is in the past', function () {
    $service = app(AfbDispatchService::class);
    $context = createOrderForClinic(now()->subDays(2));

    $readiness = $service->getAvbDispatchReadiness($context['order']);

    expect($readiness['is_ready'])->toBeFalse()
        ->and($readiness['reasons'])->toContain('Eerste onderzoekdatum is verstreken');
});

test('getAvbDispatchReadiness returns not_ready when no departments are linked', function () {
    $service = app(AfbDispatchService::class);

    $salesLead = SalesLead::factory()->create(['user_id' => auth()->id()]);
    $order = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'user_id'              => auth()->id(),
        'first_examination_at' => now()->addDays(3),
    ]);
    // No order items with clinic departments

    $readiness = $service->getAvbDispatchReadiness($order);

    expect($readiness['is_ready'])->toBeFalse()
        ->and($readiness['reasons'])->toContain('Geen kliniekafdelingen gekoppeld aan order items');
});

test('getAvbDispatchReadiness returns ready with planned_at for batch window', function () {
    $service = app(AfbDispatchService::class);
    $examAt = now()->addDays(3)->setTime(10, 0, 0);
    $context = createOrderForClinic($examAt);

    $readiness = $service->getAvbDispatchReadiness($context['order']);

    expect($readiness['is_ready'])->toBeTrue()
        ->and($readiness['is_late'])->toBeFalse()
        ->and($readiness['planned_at'])->not->toBeNull()
        ->and($readiness['planned_at']->format('H:i'))->toBe('06:00')
        ->and($readiness['planned_at']->toDateString())->toBe($examAt->copy()->subDay()->toDateString())
        ->and($readiness['reasons'])->toBeEmpty();
});

test('getAvbDispatchReadiness returns ready and is_late for late-booking window', function () {
    $service = app(AfbDispatchService::class);
    $examAt = now()->addHours(10);
    $context = createOrderForClinic($examAt);

    $readiness = $service->getAvbDispatchReadiness($context['order']);

    expect($readiness['is_ready'])->toBeTrue()
        ->and($readiness['is_late'])->toBeTrue()
        ->and($readiness['reasons'])->toBeEmpty();
});

test('getAvbDispatchReadiness returns not_ready when order is not in an allowed stage', function () {
    $service = app(AfbDispatchService::class);
    $examAt = now()->addDays(3)->setTime(10, 0, 0);
    $context = createOrderForClinic($examAt);

    $context['order']->update(['pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id()]);

    $readiness = $service->getAvbDispatchReadiness($context['order']->fresh());

    expect($readiness['is_ready'])->toBeFalse()
        ->and($readiness['reasons'])->toContain('Order staat niet in de juiste status voor AFB dispatch');
});

test('daily afb command does not queue jobs for orders not in an allowed stage', function () {
    Bus::fake();

    $targetDate = now()->addDays(3)->setTime(10, 0);
    $context = createOrderForClinic($targetDate);

    $context['order']->update(['pipeline_stage_id' => PipelineStage::ORDER_BEVESTIGD->id()]);

    $this->artisan('afb:send-daily --date='.$targetDate->toDateString())
        ->assertExitCode(0);

    Bus::assertNotDispatched(SendAfbDispatchJob::class);
});

test('late booking does not queue dispatch when order is not in an allowed stage', function () {
    Bus::fake();

    $examAt = now()->addHours(8);
    $context = createOrderForClinic($examAt);

    $context['order']->update(['pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id()]);

    // Observer may have queued late booking on ResourceOrderItem::created while stage was still allowed.
    Bus::fake();

    $queued = app(AfbDispatchService::class)->queueLateBookingForOrder($context['order']->fresh());

    expect($queued)->toBe(0);
    Bus::assertNotDispatched(SendAfbDispatchJob::class);
});

test('banner shows when order item from last afb dispatch is marked lost', function () {
    Bus::fake();
    Mail::fake();
    $this->actingAs(getDefaultAdmin(), 'user');

    $examAt = now()->addHours(10);
    $context = createOrderForClinic($examAt);
    $service = app(AfbDispatchService::class);

    // Sla een succesvolle dispatch op met het bestaande orderitem
    $existingItemIds = $context['order']->orderItems()->pluck('id')->toArray();

    $dispatch = AfbDispatch::factory()->create([
        'clinic_department_id' => $context['department']->id,
        'clinic_id'            => $context['clinic']->id,
        'status'               => AfbDispatchStatus::SUCCESS->value,
        'sent_at'              => now()->subMinutes(10),
    ]);
    AfbPersonDocument::factory()->create([
        'afb_dispatch_id' => $dispatch->id,
        'order_id'        => $context['order']->id,
        'order_item_ids'  => $existingItemIds,
    ]);

    // Geen banner nodig: alles al verstuurd
    expect($service->getAvbDispatchReadiness($context['order']->fresh())['needs_manual_send'])->toBeFalse();

    // Markeer het orderitem als LOST (simuleert verwijdering in de UI)
    OrderItem::whereIn('id', $existingItemIds)->update(['status' => OrderItemStatus::LOST->value]);

    // Nu moet de banner tonen: de kliniek moet een bijgewerkte AFB ontvangen
    expect($service->getAvbDispatchReadiness($context['order']->fresh())['needs_manual_send'])->toBeTrue()
        ->and($service->hasUnincludedActiveItems($context['order']->id, $context['department']->id))->toBeTrue();
});
