<?php

namespace Tests\Feature;

use App\Enums\PipelineStage;
use App\Models\Clinic;
use App\Models\ClinicDepartment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Webkul\Contact\Models\Person;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
    Config::set('api.keys', ['valid-api-key-123']);
});

function makeAppointmentForPerson(Person $person, array $attributes = []): array
{
    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach($person->id);

    $department = ClinicDepartment::factory()->create(['order_confirmation_note' => 'Meld u bij de balie']);
    $clinic = Clinic::find($department->clinic_id);
    $resource = Resource::factory()->create(['clinic_department_id' => $department->id]);

    $order = Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id(),
        ...$attributes,
    ]);
    $item = OrderItem::factory()->create(['order_id' => $order->id, 'person_id' => $person->id]);
    $booking = ResourceOrderItem::factory()->create([
        'orderitem_id' => $item->id,
        'resource_id'  => $resource->id,
        'from'         => now()->addDay(),
        'to'           => now()->addDay()->addHour(),
    ]);
    $order->refresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id()]);

    return [$order, $booking, $clinic, $salesLead, $item];
}

test('returns the appointment detail for the booking id', function () {
    $keycloakId = 'kc-detail-1';
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakId]);
    [$order, $booking, $clinic, $salesLead] = makeAppointmentForPerson($person);

    $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakId}/appointments/{$booking->id}")
        ->assertOk()
        ->assertJsonPath('data.id', (string) $booking->id)
        ->assertJsonPath('data.start_at', $booking->from->toIso8601String())
        ->assertJsonPath('data.end_at', $booking->to->toIso8601String())
        ->assertJsonPath('data.sale.id', $salesLead->id)
        ->assertJsonPath('data.clinic.id', $clinic->id)
        ->assertJsonPath('data.registration_note', 'Meld u bij de balie')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonStructure(['data' => ['id', 'type', 'start_at', 'end_at', 'sale', 'clinic', 'registration_note', 'items', 'preparation']]);
});

test('still accepts the legacy order-{id} format', function () {
    $keycloakId = 'kc-detail-legacy';
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakId]);
    [$order, $booking] = makeAppointmentForPerson($person);

    $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakId}/appointments/order-{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.id', (string) $booking->id);
});

test('never exposes price fields', function () {
    $keycloakId = 'kc-detail-no-prices';
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakId]);
    [, $booking] = makeAppointmentForPerson($person);

    $response = $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakId}/appointments/{$booking->id}")
        ->assertOk();

    $json = json_encode($response->json());

    expect($json)->not->toContain('price')->not->toContain('purchase');
});

test('returns 404 for an appointment belonging to another patient', function () {
    $keycloakId = 'kc-detail-owner';
    Person::factory()->create(['keycloak_user_id' => $keycloakId]);
    $otherPerson = Person::factory()->create();
    [$otherOrder, $booking] = makeAppointmentForPerson($otherPerson);

    $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakId}/appointments/{$booking->id}")
        ->assertNotFound();

    $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakId}/appointments/order-{$otherOrder->id}")
        ->assertNotFound();
});

test('returns 404 for an unknown or unrelated keycloak id', function () {
    $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson('/api/patient/kc-unknown/appointments/1')
        ->assertNotFound();
});

test('returns 404 for a booking id that does not exist', function () {
    $keycloakId = 'kc-detail-missing';
    Person::factory()->create(['keycloak_user_id' => $keycloakId]);

    $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakId}/appointments/999999")
        ->assertNotFound();
});
