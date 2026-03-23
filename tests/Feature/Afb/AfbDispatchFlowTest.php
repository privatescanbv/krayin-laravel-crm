<?php

use App\Enums\AfbDispatchType;
use App\Enums\PersonSalutation;
use App\Jobs\SendAfbDispatchJob;
use App\Models\Address;
use App\Models\AfbDispatchOrder;
use App\Models\Anamnesis;
use App\Models\Clinic;
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
        'product_id'          => $product->id,
        'clinic_description'  => 'MRT HWS zonder KM',
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
        'clinic_id' => $clinic->id,
    ]);

    ResourceOrderItem::factory()->create([
        'resource_id'  => $resource->id,
        'orderitem_id' => $orderItem->id,
        'from'         => $examAt->copy()->addMinutes(30),
        'to'           => $examAt->copy()->addMinutes(90),
    ]);

    Anamnesis::factory()->create([
        'sales_id'            => $salesLead->id,
        'lead_id'             => $salesLead->lead_id,
        'person_id'           => $person->id,
        'claustrophobia'      => true,
        'diabetes'            => false,
        'metals'              => true,
        'metals_notes'        => 'Schroef in knie',
        'heart_surgery'       => false,
        'implant'             => true,
        'implant_notes'       => 'Heupprothese links',
        'allergies'           => false,
        'glaucoma'            => false,
        'remarks'             => 'Nuchter',
        'comment_clinic'      => 'Nekpijn links, voorzichtig positioneren.',
    ]);

    return compact('clinic', 'order', 'person');
}

test('afb generator maps crm fields into afb layout', function () {
    $context = createOrderForClinic(Carbon::parse('2026-03-31 09:30:00'));

    $generator = app(AfbDocumentGenerator::class);
    $rendered = $generator->renderHtmlForOrderAndClinic($context['order']->fresh(), $context['clinic']);
    $html = $rendered['html'];

    expect($html)
        ->toContain('Aanvraagformulier Behandeling')
        ->toContain('Evidia - Augusta Klinik')
        ->toContain('ORD-TEST-1001')
        ->toContain('Lara')
        ->toContain('Muller - de Boer')
        ->toContain('MRT HWS zonder KM')
        ->toContain('Ja')
        ->toContain('Nee')
        ->toContain('Schroef in knie')
        ->toContain('Nekpijn links, voorzichtig positioneren.')
        ->not->toContain('Kode')
        ->not->toContain('Verk.nr');
});

test('daily afb command queues one batch job per clinic', function () {
    Bus::fake();

    $targetDate = now()->addDays(3)->setTime(10, 0);
    $context = createOrderForClinic($targetDate);

    $this->artisan('afb:send-daily --date='.$targetDate->toDateString())
        ->assertExitCode(0);

    Bus::assertDispatched(SendAfbDispatchJob::class, function (SendAfbDispatchJob $job) use ($context) {
        return $job->clinicId === $context['clinic']->id
            && $job->type === AfbDispatchType::BATCH->value
            && $job->orderIds === [$context['order']->id];
    });
});

test('late booking planning queues individual afb dispatch', function () {
    Bus::fake();

    $examAt = now()->addHours(8);
    $context = createOrderForClinic($examAt);

    Bus::assertDispatched(SendAfbDispatchJob::class, function (SendAfbDispatchJob $job) use ($context) {
        return $job->clinicId === $context['clinic']->id
            && $job->type === AfbDispatchType::INDIVIDUAL->value
            && $job->orderIds === [$context['order']->id];
    });
});

test('dispatch service prevents duplicate send to same clinic', function () {
    Mail::fake();

    $context = createOrderForClinic(now()->addDays(2)->setTime(11, 0));
    $service = app(AfbDispatchService::class);

    $service->sendDispatch(
        clinicId: $context['clinic']->id,
        orderIds: [$context['order']->id],
        type: AfbDispatchType::INDIVIDUAL,
        attempt: 1
    );

    $service->sendDispatch(
        clinicId: $context['clinic']->id,
        orderIds: [$context['order']->id],
        type: AfbDispatchType::INDIVIDUAL,
        attempt: 1
    );

    expect(AfbDispatchOrder::query()
        ->where('clinic_id', $context['clinic']->id)
        ->where('order_id', $context['order']->id)
        ->count())->toBe(1);

    expect(Email::query()->where('clinic_id', $context['clinic']->id)->count())->toBe(1);

    $context['order']->refresh();
    expect($context['order']->afb_sent_to_clinic_id)->toBe($context['clinic']->id);

    Mail::assertSent(EmailMailable::class, 1);
});

test('clinic view contains afb verzendingen navigation', function () {
    $clinic = Clinic::factory()->create();

    $response = $this->get(route('admin.clinics.view', $clinic->id));

    $response->assertOk();
    $response->assertSee('AFB verzendingen');
});
