<?php

use App\Events\OrderMarkedAsSent;
use App\Models\Order;
use App\Models\PatientNotification;
use App\Models\SalesLead;
use Illuminate\Support\Str;
use Webkul\Contact\Models\Person;

it('stores confirmation PDF as activity and creates patient notification', function () {
    config(['api.keys' => ['test-api-key']]);

    $keycloakUserId = (string) Str::uuid();

    $person = Person::factory()->create([
        'keycloak_user_id' => $keycloakUserId,
        'is_active'        => true,
    ]);

    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach($person->id);

    $order = Order::factory()->create([
        'sales_lead_id'               => $salesLead->id,
        'confirmation_letter_content' => '<html><body><h1>Order Bevestiging</h1></body></html>',
    ]);

    // Dispatch event - triggers PDF listener which triggers notification listener
    OrderMarkedAsSent::dispatch($order, null);

    // Verify document is available via API
    $response = $this->getJson(
        "/api/patient/{$keycloakUserId}/documents",
        ['X-API-KEY' => 'test-api-key']
    );

    $response->assertOk();
    $response->assertJsonPath('meta.total', 1);
    $response->assertJsonPath('data.0.type', 'order_confirmation');

    // Verify patient notification was created
    $notification = PatientNotification::where('patient_id', $person->id)->first();
    expect($notification)->not->toBeNull();
    expect($notification->type)->toBe('document');
    expect($notification->dismissable)->toBeFalse();
});
