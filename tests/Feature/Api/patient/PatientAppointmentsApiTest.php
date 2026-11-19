<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\PipelineStage;
use App\Models\Clinic;
use App\Models\ClinicDepartment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use App\Models\SalesLead;
use App\Repositories\OrderRepository;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Person;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
    Config::set('api.keys', ['valid-api-key-123']);
});

test('patient appointments endpoint returns planned/approved orders as appointments', function () {
    $keycloakId = 'kc-user-appointments-1';
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakId]);

    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach($person->id);

    $clinicA = Clinic::factory()->create(['name' => 'Clinic A']);
    $clinicB = Clinic::factory()->create(['name' => 'Clinic B']);
    $resourceA = Resource::factory()->create(['clinic_id' => $clinicA->id]);
    $resourceB = Resource::factory()->create(['clinic_id' => $clinicB->id]);

    $futureOrder = Order::factory()->create([
        'sales_lead_id'         => $salesLead->id,
        'pipeline_stage_id'     => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at'  => now()->addDay(),
    ]);

    // Create two bookings; the earliest ResourceOrderItem.from determines clinic_id/clinic_label.
    $futureOrderItem1 = OrderItem::factory()->create(['order_id' => $futureOrder->id, 'person_id' => $person->id]);
    $futureOrderItem2 = OrderItem::factory()->create(['order_id' => $futureOrder->id, 'person_id' => $person->id]);
    ResourceOrderItem::factory()->create([
        'orderitem_id' => $futureOrderItem1->id,
        'resource_id'  => $resourceB->id,
        'from'         => now()->addHours(2),
        'to'           => now()->addHours(3),
    ]);
    $futureBooking = ResourceOrderItem::factory()->create([
        'orderitem_id' => $futureOrderItem2->id,
        'resource_id'  => $resourceA->id,
        'from'         => now()->addHours(1), // earliest
        'to'           => now()->addHours(2),
    ]);

    // Creating order items/bookings may trigger status recalculation via observers.
    // Force back to an API-visible state for this endpoint.
    $futureOrder->refresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_WACHTEN_UITVOERING->id()]);

    $pastOrder = Order::factory()->create([
        'sales_lead_id'         => $salesLead->id,
        'pipeline_stage_id'     => PipelineStage::ORDER_GEWONNEN->id(),
        'first_examination_at'  => now()->subDay(),
    ]);

    $pastOrderItem = OrderItem::factory()->create(['order_id' => $pastOrder->id, 'person_id' => $person->id]);
    $pastBooking = ResourceOrderItem::factory()->create([
        'orderitem_id' => $pastOrderItem->id,
        'resource_id'  => $resourceB->id,
        'from'         => now()->subHours(5),
        'to'           => now()->subHours(4),
    ]);

    $pastOrder->refresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id()]);

    expect($futureOrder->fresh()->pipeline_stage_id)->toBe(PipelineStage::ORDER_WACHTEN_UITVOERING->id())
        ->and($pastOrder->fresh()->pipeline_stage_id)->toBe(PipelineStage::ORDER_GEWONNEN->id());

    // Slot-only: no first_examination_at override but earliest booking defines the appointment.
    $slotOnlyOrder = Order::factory()->create([
        'sales_lead_id'         => $salesLead->id,
        'pipeline_stage_id'     => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at'  => null,
    ]);
    $slotOnlyItem = OrderItem::factory()->create(['order_id' => $slotOnlyOrder->id, 'person_id' => $person->id]);
    $slotOnlyBooking = ResourceOrderItem::factory()->create([
        'orderitem_id' => $slotOnlyItem->id,
        'resource_id'  => $resourceA->id,
        'from'         => now()->addDay()->setTime(15, 0),
        'to'           => now()->addDay()->setTime(16, 0),
    ]);
    $slotOnlyOrder->refresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_WACHTEN_UITVOERING->id()]);

    // Should be ignored (wrong status)
    Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id'    => PipelineStage::ORDER_CONFIRM->id(),
        'first_examination_at' => now()->addDays(2),
    ]);

    // Should be ignored (no first_examination_at)
    Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id'    => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at' => null,
    ]);

    $response = $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakId}/appointments");

    $response->assertOk();
    $response->assertJsonCount(3, 'data');
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'patient_id',
                'clinic_id',
                'clinic_ref',
                'start_at',
                'end_at',
                'created_at',
            ],
        ],
    ]);

    $response->assertJsonFragment([
        'id'           => (string) $futureBooking->id,
        'patient_id'   => (string) $person->id,
        'clinic_id'    => (string) $clinicA->id,
    ]);

    $response->assertJsonFragment([
        'clinic_ref' => [
            'id'      => $clinicA->id,
            'name'    => $clinicA->name,
            'address' => null,
        ],
    ]);

    $response->assertJsonFragment([
        'id'           => (string) $slotOnlyBooking->id,
        'patient_id'   => (string) $person->id,
        'clinic_id'    => (string) $clinicA->id,
    ]);

    $response->assertJsonFragment([
        'id'           => (string) $pastBooking->id,
        'patient_id'   => (string) $person->id,
        'clinic_id'    => (string) $clinicB->id,
    ]);

    $response->assertJsonFragment([
        'clinic_ref' => [
            'id'      => $clinicB->id,
            'name'    => $clinicB->name,
            'address' => null,
        ],
    ]);
});

test('clinic is derived from first booking for patient when order is not combined', function () {
    $keycloakId = 'kc-user-appointments-non-combined';
    $patient = Person::factory()->create(['keycloak_user_id' => $keycloakId]);
    $otherPerson = Person::factory()->create();

    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach([$patient->id, $otherPerson->id]);

    $clinicPatient = Clinic::factory()->create(['name' => 'Clinic Patient']);
    $clinicOther = Clinic::factory()->create(['name' => 'Clinic Other']);
    $resourcePatient = Resource::factory()->create(['clinic_id' => $clinicPatient->id]);
    $resourceOther = Resource::factory()->create(['clinic_id' => $clinicOther->id]);

    $order = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id'    => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'combine_order'        => false,
        'first_examination_at' => now()->addDay(),
    ]);

    // Earliest booking belongs to OTHER person; patient's booking is later.
    $oiOther = OrderItem::factory()->create(['order_id' => $order->id, 'person_id' => $otherPerson->id]);
    $oiPatient = OrderItem::factory()->create(['order_id' => $order->id, 'person_id' => $patient->id]);

    ResourceOrderItem::factory()->create([
        'orderitem_id' => $oiOther->id,
        'resource_id'  => $resourceOther->id,
        'from'         => now()->addHours(1), // earliest overall, but not patient's
        'to'           => now()->addHours(2),
    ]);
    $patientBooking = ResourceOrderItem::factory()->create([
        'orderitem_id' => $oiPatient->id,
        'resource_id'  => $resourcePatient->id,
        'from'         => now()->addHours(2),
        'to'           => now()->addHours(3),
    ]);

    // Force back to an API-visible state (order observers can recalculate status).
    $order->refresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_WACHTEN_UITVOERING->id()]);

    $response = $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakId}/appointments");

    $response->assertOk();
    $response->assertJsonFragment([
        'id'           => (string) $patientBooking->id,
        'patient_id'   => (string) $patient->id,
        // Should pick patient's clinic, not the earliest overall clinic.
        'clinic_id'    => (string) $clinicPatient->id,
    ]);
    $response->assertJsonFragment([
        'clinic_ref' => [
            'id'      => $clinicPatient->id,
            'name'    => $clinicPatient->name,
            'address' => null,
        ],
    ]);
});

test('patient appointments endpoint supports future/past filter', function () {
    $keycloakId = 'kc-user-appointments-2';
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakId]);

    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach($person->id);

    $clinicFuture = Clinic::factory()->create(['name' => 'Clinic Future']);
    $clinicPast = Clinic::factory()->create(['name' => 'Clinic Past']);
    $resourceFuture = Resource::factory()->create(['clinic_id' => $clinicFuture->id]);
    $resourcePast = Resource::factory()->create(['clinic_id' => $clinicPast->id]);

    $futureOrder = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id'    => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at' => now()->addHours(6),
    ]);

    $futureOrderItem = OrderItem::factory()->create(['order_id' => $futureOrder->id, 'person_id' => $person->id]);
    $futureBooking = ResourceOrderItem::factory()->create([
        'orderitem_id' => $futureOrderItem->id,
        'resource_id'  => $resourceFuture->id,
        'from'         => now()->addHours(6),
        'to'           => now()->addHours(7),
    ]);

    $futureOrder->refresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_WACHTEN_UITVOERING->id()]);

    $pastOrder = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id'    => PipelineStage::ORDER_GEWONNEN->id(),
        'first_examination_at' => now()->subHours(6),
    ]);

    $pastOrderItem = OrderItem::factory()->create(['order_id' => $pastOrder->id, 'person_id' => $person->id]);
    $pastBooking = ResourceOrderItem::factory()->create([
        'orderitem_id' => $pastOrderItem->id,
        'resource_id'  => $resourcePast->id,
        'from'         => now()->subHours(6),
        'to'           => now()->subHours(5),
    ]);

    $pastOrder->refresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id()]);

    expect(app(OrderRepository::class)->getPatientAppointmentOrdersForPerson($person, null, now())->count())
        ->toBe(2);

    $futureResponse = $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakId}/appointments?filter=future");

    $futureResponse->assertOk();
    $futureResponse->assertJsonCount(1, 'data');
    $futureResponse->assertJsonFragment([
        'id'           => (string) $futureBooking->id,
        'patient_id'   => (string) $person->id,
        'clinic_id'    => (string) $clinicFuture->id,
    ]);

    $futureResponse->assertJsonFragment([
        'clinic_ref' => [
            'id'      => $clinicFuture->id,
            'name'    => $clinicFuture->name,
            'address' => null,
        ],
    ]);

    $pastResponse = $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakId}/appointments?filter=past");

    $pastResponse->assertOk();
    $pastResponse->assertJsonCount(1, 'data');
    $pastResponse->assertJsonFragment([
        'id'           => (string) $pastBooking->id,
        'patient_id'   => (string) $person->id,
        'clinic_id'    => (string) $clinicPast->id,
    ]);

    $pastResponse->assertJsonFragment([
        'clinic_ref' => [
            'id'      => $clinicPast->id,
            'name'    => $clinicPast->name,
            'address' => null,
        ],
    ]);
});

test('verloren order is not shown as patient appointment', function () {
    $keycloakId = 'kc-user-appointments-verloren';
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakId]);

    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach($person->id);

    $verlorenOrder = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id'    => PipelineStage::ORDER_VERLOREN->id(),
        'first_examination_at' => now()->addDay(),
    ]);
    $verlorenOrder->refresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_VERLOREN->id()]);

    $verlorenHerniaOrder = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id'    => PipelineStage::ORDER_VERLOREN_HERNIA->id(),
        'first_examination_at' => now()->addDay(),
    ]);
    $verlorenHerniaOrder->refresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_VERLOREN_HERNIA->id()]);

    $response = $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakId}/appointments");

    $response->assertOk();
    $response->assertJsonCount(0, 'data');
});

test('ingepland order is shown as patient appointment', function () {
    $keycloakId = 'kc-user-appointments-ingepland';
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakId]);

    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach($person->id);

    $clinic = Clinic::factory()->create(['name' => 'Clinic Ingepland']);
    $resource = Resource::factory()->create(['clinic_id' => $clinic->id]);

    $ingeplandOrder = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id'    => PipelineStage::ORDER_INGEPLAND->id(),
        'first_examination_at' => now()->addDay(),
    ]);

    $orderItem = OrderItem::factory()->create(['order_id' => $ingeplandOrder->id, 'person_id' => $person->id]);
    $booking = ResourceOrderItem::factory()->create([
        'orderitem_id' => $orderItem->id,
        'resource_id'  => $resource->id,
        'from'         => now()->addDay(),
        'to'           => now()->addDay()->addHour(),
    ]);

    $ingeplandOrder->refresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id()]);

    $response = $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakId}/appointments");

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment([
        'id'         => (string) $booking->id,
        'patient_id' => (string) $person->id,
        'clinic_id'  => (string) $clinic->id,
    ]);
});

function plannedOrderWithBookings(string $keycloakId, array $bookings): array
{
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakId]);
    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach($person->id);

    $order = Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id(),
    ]);

    foreach ($bookings as $booking) {
        $department = ClinicDepartment::factory()->create(['order_confirmation_note' => $booking['note']]);
        $resource = Resource::factory()->create(['clinic_department_id' => $department->id]);
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'person_id' => $person->id]);
        ResourceOrderItem::factory()->create([
            'orderitem_id' => $item->id,
            'resource_id'  => $resource->id,
            'from'         => $booking['from'],
            'to'           => $booking['from']->copy()->addHour(),
        ]);
        if (isset($booking['status'])) {
            $item->update(['status' => $booking['status']]);
        }
        $departments[] = $department;
    }

    $order->refresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id()]);

    return [$order, $departments ?? []];
}

test('appointment exposes the registration note of the first planned booking', function () {
    $day = now()->addDays(3)->setTime(9, 0);
    [, $departments] = plannedOrderWithBookings('kc-note-1', [
        ['note' => 'Later op de dag', 'from' => $day->copy()->addHours(3)],
        ['note' => 'Meld u bij de receptie van Evidia', 'from' => $day],
    ]);

    $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson('/api/patient/kc-note-1/appointments')
        ->assertOk()
        ->assertJsonPath('data.0.registration_note', 'Meld u bij de receptie van Evidia')
        ->assertJsonPath('data.0.clinic_id', (string) $departments[1]->clinic_id);
});

test('lost first booking is skipped when determining clinic and registration note', function () {
    $day = now()->addDays(3)->setTime(9, 0);
    [, $departments] = plannedOrderWithBookings('kc-note-2', [
        ['note' => 'Vervallen product', 'from' => $day, 'status' => OrderItemStatus::LOST->value],
        ['note' => 'Ingepland product', 'from' => $day->copy()->addHour()],
    ]);

    $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson('/api/patient/kc-note-2/appointments')
        ->assertOk()
        ->assertJsonPath('data.0.registration_note', 'Ingepland product')
        ->assertJsonPath('data.0.clinic_id', (string) $departments[1]->clinic_id);
});

test('registration note is null when the department has none', function () {
    plannedOrderWithBookings('kc-note-3', [
        ['note' => null, 'from' => now()->addDays(3)],
    ]);

    $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson('/api/patient/kc-note-3/appointments')
        ->assertOk()
        ->assertJsonPath('data.0.registration_note', null);
});

test('clinic is resolved from the already-loaded booking, without a redundant extra lookup', function () {
    // Each appointment's booking-resolution query (unchanged from before this ticket) already
    // eager-loads its clinic; the Resource must use that instead of re-querying Clinic::find().
    // So exactly one query should touch "clinics" per appointment, not two.
    $keycloakId = 'kc-query-count';
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakId]);
    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach($person->id);

    $makeAppointment = function () use ($salesLead, $person) {
        $clinic = Clinic::factory()->create();
        $resource = Resource::factory()->create(['clinic_id' => $clinic->id]);
        $order = Order::factory()->create([
            'sales_lead_id'     => $salesLead->id,
            'pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id(),
        ]);
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'person_id' => $person->id]);
        ResourceOrderItem::factory()->create([
            'orderitem_id' => $item->id,
            'resource_id'  => $resource->id,
            'from'         => now()->addDay(),
            'to'           => now()->addDay()->addHour(),
        ]);
        $order->refresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id()]);
    };

    $makeAppointment();
    $makeAppointment();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakId}/appointments")
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $clinicQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], '"clinics"'));

    expect($clinicQueries)->toHaveCount(2);
});
