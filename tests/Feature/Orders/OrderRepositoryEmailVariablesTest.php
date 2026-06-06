<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Models\Clinic;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use App\Models\SalesLead;
use App\Repositories\OrderRepository;
use App\Services\Mail\EmailTemplateRenderingService;
use Carbon\Carbon;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Contact\Models\Person;
use Webkul\Product\Models\Product;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

test('resolveEmailVariablesForOrder uses earliest slot per person when person id is passed', function () {
    $personEarly = Person::factory()->create(['name' => 'Early Patiënt']);
    $personLate = Person::factory()->create(['name' => 'Late Patiënt']);

    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach([$personEarly->id, $personLate->id]);

    $clinicA = Clinic::factory()->create(['name' => 'Kliniek A']);
    $clinicB = Clinic::factory()->create(['name' => 'Kliniek B']);
    $resourceA = Resource::factory()->create(['clinic_id' => $clinicA->id]);
    $resourceB = Resource::factory()->create(['clinic_id' => $clinicB->id]);

    $appointmentDay = Carbon::parse('2026-08-20 12:00:00', config('app.timezone'));

    $order = Order::factory()->create([
        'sales_lead_id'         => $salesLead->id,
        'first_examination_at'  => null,
        'first_examination_time'=> null,
    ]);

    $itemLate = OrderItem::factory()->create([
        'order_id'  => $order->id,
        'person_id' => $personLate->id,
    ]);
    ResourceOrderItem::factory()->create([
        'orderitem_id' => $itemLate->id,
        'resource_id'  => $resourceB->id,
        'from'         => $appointmentDay->copy()->setTime(14, 15),
        'to'           => $appointmentDay->copy()->setTime(15, 0),
    ]);

    $itemEarly = OrderItem::factory()->create([
        'order_id'  => $order->id,
        'person_id' => $personEarly->id,
    ]);
    ResourceOrderItem::factory()->create([
        'orderitem_id' => $itemEarly->id,
        'resource_id'  => $resourceA->id,
        'from'         => $appointmentDay->copy()->setTime(9, 30),
        'to'           => $appointmentDay->copy()->setTime(10, 0),
    ]);

    $repo = app(OrderRepository::class);

    $forEarly = $repo->resolveEmailVariablesForOrder($order->id, $personEarly->id);

    expect($forEarly['datum_afspraak'])->toBe('20 augustus 2026')
        ->and($forEarly['tijd_afspraak'])->toBe('09:30')
        ->and($forEarly['plaats_afspraak'])->toContain('Kliniek A');

    $forLate = $repo->resolveEmailVariablesForOrder($order->id, $personLate->id);

    expect($forLate['tijd_afspraak'])->toBe('14:15')
        ->and($forLate['plaats_afspraak'])->toContain('Kliniek B');
});

test('resolveEmailVariablesForOrder leaves appointment vars empty for person without bookings when another person has slots', function () {
    $personWith = Person::factory()->create();
    $personWithout = Person::factory()->create();

    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach([$personWith->id, $personWithout->id]);

    $clinic = Clinic::factory()->create(['name' => 'Kliniek X']);
    $resource = Resource::factory()->create(['clinic_id' => $clinic->id]);

    $order = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'first_examination_at' => null,
    ]);

    $item = OrderItem::factory()->create([
        'order_id'  => $order->id,
        'person_id' => $personWith->id,
    ]);
    ResourceOrderItem::factory()->create([
        'orderitem_id' => $item->id,
        'resource_id'  => $resource->id,
        'from'         => Carbon::parse('2026-09-01 10:00:00'),
        'to'           => Carbon::parse('2026-09-01 11:00:00'),
    ]);

    $repo = app(OrderRepository::class);

    $forWithout = $repo->resolveEmailVariablesForOrder($order->id, $personWithout->id);

    expect($forWithout['datum_afspraak'])->toBe('')
        ->and($forWithout['tijd_afspraak'])->toBe('')
        ->and($forWithout['plaats_afspraak'])->toBe('');

    $forWith = $repo->resolveEmailVariablesForOrder($order->id, $personWith->id);

    expect($forWith['tijd_afspraak'])->toBe('10:00');
});

test('resolveEmailVariablesForOrder without person id uses order-wide firstExaminationCarbon', function () {
    $personA = Person::factory()->create();
    $personB = Person::factory()->create();

    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach([$personA->id, $personB->id]);

    $clinic = Clinic::factory()->create();
    $resource = Resource::factory()->create(['clinic_id' => $clinic->id]);

    $order = Order::factory()->create([
        'sales_lead_id'         => $salesLead->id,
        'first_examination_at'  => '2026-10-05',
        'first_examination_time'=> null,
    ]);

    $itemB = OrderItem::factory()->create(['order_id' => $order->id, 'person_id' => $personB->id]);
    ResourceOrderItem::factory()->create([
        'orderitem_id' => $itemB->id,
        'resource_id'  => $resource->id,
        'from'         => Carbon::parse('2026-10-05 16:45:00'),
        'to'           => Carbon::parse('2026-10-05 17:30:00'),
    ]);

    $itemA = OrderItem::factory()->create(['order_id' => $order->id, 'person_id' => $personA->id]);
    ResourceOrderItem::factory()->create([
        'orderitem_id' => $itemA->id,
        'resource_id'  => $resource->id,
        'from'         => Carbon::parse('2026-10-05 08:15:00'),
        'to'           => Carbon::parse('2026-10-05 09:00:00'),
    ]);

    $repo = app(OrderRepository::class);

    $vars = $repo->resolveEmailVariablesForOrder($order->id, null);

    // Order-wide: date from override, time from earliest non-lost slot (08:15), same as firstExaminationCarbon().
    expect($order->fresh()->firstExaminationCarbon()?->format('Y-m-d H:i'))->toBe('2026-10-05 08:15')
        ->and($vars['tijd_afspraak'])->toBe('08:15')
        ->and($vars['datum_afspraak'])->toBe('05 oktober 2026');
});

test('order items table excludes lost order lines', function () {
    $person = Person::factory()->create();
    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach($person->id);

    $order = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
        'total_price'   => 150,
    ]);

    OrderItem::factory()->create([
        'order_id'    => $order->id,
        'name'        => 'Actieve MRI',
        'total_price' => 100,
    ]);

    OrderItem::factory()->create([
        'order_id'    => $order->id,
        'name'        => 'Verwijderde CT',
        'total_price' => 50,
        'status'      => OrderItemStatus::LOST->value,
    ]);

    $vars = app(EmailTemplateRenderingService::class)
        ->resolveVariablesFromEntities(['order' => $order->id]);

    expect($vars['order_items_table'])
        ->toContain('Actieve MRI')
        ->not->toContain('Verwijderde CT');
});

test('order mail variables exclude lost lines from summary and appointments tables', function () {
    $person = Person::factory()->create();
    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach($person->id);

    $clinic = Clinic::factory()->create(['name' => 'Scan Kliniek']);
    $resource = Resource::factory()->create(['clinic_id' => $clinic->id]);

    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    $activeItem = OrderItem::factory()->create([
        'order_id'    => $order->id,
        'person_id'   => $person->id,
        'name'        => 'Actieve MRI',
        'description' => 'Actieve MRI onderzoek',
        'product_id'  => Product::factory()->create()->id,
    ]);
    ResourceOrderItem::factory()->create([
        'orderitem_id' => $activeItem->id,
        'resource_id'  => $resource->id,
        'from'         => Carbon::parse('2026-09-01 10:00:00'),
        'to'           => Carbon::parse('2026-09-01 11:00:00'),
    ]);

    $lostItem = OrderItem::factory()->create([
        'order_id'    => $order->id,
        'person_id'   => $person->id,
        'name'        => 'Verwijderde CT',
        'description' => 'Verwijderde CT onderzoek',
        'product_id'  => Product::factory()->create()->id,
    ]);
    ResourceOrderItem::factory()->create([
        'orderitem_id' => $lostItem->id,
        'resource_id'  => $resource->id,
        'from'         => Carbon::parse('2026-09-01 14:00:00'),
        'to'           => Carbon::parse('2026-09-01 15:00:00'),
    ]);
    $lostItem->update(['status' => OrderItemStatus::LOST->value]);

    $vars = app(EmailTemplateRenderingService::class)
        ->resolveVariablesFromEntities(['order' => $order->id]);

    expect($vars['order_summary_table'])
        ->toContain('Actieve MRI onderzoek')
        ->not->toContain('Verwijderde CT onderzoek')
        ->and($vars['appointments_by_person'])
        ->not->toContain('Verwijderde CT')
        ->and($vars['afspraken_tabel'])
        ->not->toContain('Verwijderde CT');
});

test('displayableOrderItems excludes lost lines when orderItems are eager loaded', function () {
    $order = Order::factory()->create();

    OrderItem::factory()->create(['order_id' => $order->id, 'name' => 'Actief']);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'name'     => 'Verloren',
        'status'   => OrderItemStatus::LOST->value,
    ]);

    $order->load('orderItems');

    expect($order->displayableOrderItems()->pluck('name')->all())->toBe(['Actief']);
});
