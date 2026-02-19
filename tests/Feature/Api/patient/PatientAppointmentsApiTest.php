<?php

namespace Tests\Feature;

use App\Enums\PipelineStage;
use App\Models\Clinic;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use App\Models\SalesLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Webkul\Contact\Models\Person;

uses(RefreshDatabase::class);

beforeEach(function () {
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
    $futureOrderItem1 = OrderItem::factory()->create(['order_id' => $futureOrder->id]);
    $futureOrderItem2 = OrderItem::factory()->create(['order_id' => $futureOrder->id]);
    ResourceOrderItem::factory()->create([
        'orderitem_id' => $futureOrderItem1->id,
        'resource_id'  => $resourceB->id,
        'from'         => now()->addHours(2),
        'to'           => now()->addHours(3),
    ]);
    ResourceOrderItem::factory()->create([
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

    $pastOrderItem = OrderItem::factory()->create(['order_id' => $pastOrder->id]);
    ResourceOrderItem::factory()->create([
        'orderitem_id' => $pastOrderItem->id,
        'resource_id'  => $resourceB->id,
        'from'         => now()->subHours(5),
        'to'           => now()->subHours(4),
    ]);

    $pastOrder->refresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id()]);

    expect($futureOrder->fresh()->pipeline_stage_id)->toBe(PipelineStage::ORDER_WACHTEN_UITVOERING->id())
        ->and($pastOrder->fresh()->pipeline_stage_id)->toBe(PipelineStage::ORDER_GEWONNEN->id());

    // Should be ignored (wrong status)
    Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id'    => PipelineStage::ORDER_VOORBEREIDEN->id(),
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
    $response->assertJsonCount(2, 'data');
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'patient_id',
                'clinic_id',
                'clinic_ref',
                'start_at',
                'is_remote',
                'created_at',
            ],
        ],
    ]);

    $response->assertJsonFragment([
        'id'           => 'order-'.$futureOrder->id,
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
        'id'           => 'order-'.$pastOrder->id,
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
    ResourceOrderItem::factory()->create([
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
        'id'           => 'order-'.$order->id,
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

    $futureOrderItem = OrderItem::factory()->create(['order_id' => $futureOrder->id]);
    ResourceOrderItem::factory()->create([
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

    $pastOrderItem = OrderItem::factory()->create(['order_id' => $pastOrder->id]);
    ResourceOrderItem::factory()->create([
        'orderitem_id' => $pastOrderItem->id,
        'resource_id'  => $resourcePast->id,
        'from'         => now()->subHours(6),
        'to'           => now()->subHours(5),
    ]);

    $pastOrder->refresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id()]);

    expect(Order::query()
        ->whereIn('pipeline_stage_id', [PipelineStage::ORDER_WACHTEN_UITVOERING->id(), PipelineStage::ORDER_GEWONNEN->id()])
        ->whereNotNull('first_examination_at')
        ->whereHas('salesLead.persons', fn ($q) => $q->whereKey($person->id))
        ->count()
    )->toBe(2);

    $futureResponse = $this->withHeaders(['X-API-KEY' => 'valid-api-key-123'])
        ->getJson("/api/patient/{$keycloakId}/appointments?filter=future");

    $futureResponse->assertOk();
    $futureResponse->assertJsonCount(1, 'data');
    $futureResponse->assertJsonFragment([
        'id'           => 'order-'.$futureOrder->id,
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
        'id'           => 'order-'.$pastOrder->id,
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
