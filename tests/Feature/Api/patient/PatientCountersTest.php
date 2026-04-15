<?php

use App\Enums\PatientMessageSenderType;
use App\Enums\PipelineStage;
use App\Models\Order;
use App\Models\PatientMessage;
use App\Models\SalesLead;
use App\Services\FormService;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
    config(['api.keys' => ['test-api-key']]);

    // Mock FormService so tests don't make real HTTP calls to the forms API.
    $this->mock(FormService::class, function ($mock) {
        $mock->shouldReceive('getOpenForms')->andReturn(0);
    });

    // Disable Activity events to prevent automatic creation of extra PatientMessage
    // records via observers, which would interfere with message counter assertions.
    Activity::unsetEventDispatcher();
});

// ─── Helpers ──────────────────────────────────────────────────────────────

function countersUrl(string $keycloakUserId): string
{
    return "/api/patient/{$keycloakUserId}/counters";
}

// ─── Response shape ────────────────────────────────────────────────────────

test('counters endpoint returns expected keys', function () {
    $keycloakUserId = (string) Str::uuid();
    Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $response = $this->getJson(countersUrl($keycloakUserId), ['X-API-KEY' => 'test-api-key']);

    $response->assertOk();
    $response->assertJsonStructure([
        'new_messages_count',
        'new_appointments_count',
        'new_docs_count',
    ]);
    $response->assertJson([
        'new_messages_count'     => 0,
        'new_appointments_count' => 0,
        'new_docs_count'         => 0,
    ]);
});

test('unknown keycloak id returns 404', function () {
    $response = $this->getJson(
        countersUrl('non-existent-uuid'),
        ['X-API-KEY' => 'test-api-key']
    );

    $response->assertNotFound();
});

// ─── Messages ─────────────────────────────────────────────────────────────

test('new_messages_count counts unread staff and system messages', function () {
    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    // Unread from staff — should be counted
    PatientMessage::factory()->create([
        'person_id'   => $person->id,
        'sender_type' => PatientMessageSenderType::STAFF,
        'is_read'     => false,
    ]);

    // Unread from system — should be counted
    PatientMessage::factory()->create([
        'person_id'   => $person->id,
        'sender_type' => PatientMessageSenderType::SYSTEM,
        'is_read'     => false,
    ]);

    // Already read — must not be counted
    PatientMessage::factory()->create([
        'person_id'   => $person->id,
        'sender_type' => PatientMessageSenderType::STAFF,
        'is_read'     => true,
    ]);

    // Unread message sent BY the patient — must not be counted
    PatientMessage::factory()->create([
        'person_id'   => $person->id,
        'sender_type' => PatientMessageSenderType::PATIENT,
        'is_read'     => false,
    ]);

    $response = $this->getJson(countersUrl($keycloakUserId), ['X-API-KEY' => 'test-api-key']);

    $response->assertOk();
    $response->assertJsonPath('new_messages_count', 2);
});

// ─── Appointments ─────────────────────────────────────────────────────────

test('new_appointments_count counts future orders', function () {
    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach($person->id);

    // Future eligible order — should be counted
    $futureOrder = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'order_number'         => 'ORD-FUTURE-1',
        'pipeline_stage_id'    => PipelineStage::ORDER_BEVESTIGD->id(),
        'first_examination_at' => now()->addDay(),
    ]);
    $futureOrder->refresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_WACHTEN_UITVOERING->id()]);

    // Past order — must not be counted
    $pastOrder = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'order_number'         => 'ORD-PAST-1',
        'pipeline_stage_id'    => PipelineStage::ORDER_BEVESTIGD->id(),
        'first_examination_at' => now()->subDay(),
    ]);
    $pastOrder->refresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id()]);

    // Not-eligible stage — must not be counted
    Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'order_number'         => 'ORD-NOT-ELIGIBLE-1',
        'pipeline_stage_id'    => PipelineStage::ORDER_CONFIRM->id(),
        'first_examination_at' => now()->addDay(),
    ]);

    $response = $this->getJson(countersUrl($keycloakUserId), ['X-API-KEY' => 'test-api-key']);

    $response->assertOk();
    $response->assertJsonPath('new_appointments_count', 1);
});

// ─── Combined ─────────────────────────────────────────────────────────────

test('counters combines messages and appointments independently', function () {
    $keycloakUserId = (string) Str::uuid();
    $person = Person::factory()->create(['keycloak_user_id' => $keycloakUserId]);

    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach($person->id);

    // 2 unread messages
    PatientMessage::factory()->count(2)->create([
        'person_id'   => $person->id,
        'sender_type' => PatientMessageSenderType::STAFF,
        'is_read'     => false,
    ]);

    // 1 future appointment
    $order = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'order_number'         => 'ORD-FUTURE-2',
        'pipeline_stage_id'    => PipelineStage::ORDER_BEVESTIGD->id(),
        'first_examination_at' => now()->addDay(),
    ]);
    $order->refresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_WACHTEN_UITVOERING->id()]);

    $response = $this->getJson(countersUrl($keycloakUserId), ['X-API-KEY' => 'test-api-key']);

    $response->assertOk();
    $response->assertJson([
        'new_messages_count'     => 2,
        'new_appointments_count' => 1,
    ]);
});
