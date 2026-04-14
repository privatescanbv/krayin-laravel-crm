<?php

use App\Enums\ActivityType;
use App\Events\OrderMarkedAsSent;
use App\Models\Order;
use App\Models\OrderPersonConfirmation;
use App\Models\SalesLead;
use Illuminate\Support\Str;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;

it('skips single PDF when combine_order is false', function () {
    $person = Person::factory()->create([
        'keycloak_user_id' => (string) Str::uuid(),
        'is_active'        => true,
    ]);

    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach($person->id);

    $order = Order::factory()->create([
        'sales_lead_id'               => $salesLead->id,
        'combine_order'               => false,
        'confirmation_letter_content' => '<html><body>Test</body></html>',
    ]);

    OrderMarkedAsSent::dispatch($order, null);

    $activities = Activity::where('order_id', $order->id)
        ->where('type', ActivityType::FILE)
        ->get();

    expect($activities)->toHaveCount(0);
});

it('creates single PDF when combine_order is true', function () {
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
        'combine_order'               => true,
        'confirmation_letter_content' => '<html><body><h1>Order Bevestiging</h1></body></html>',
    ]);

    OrderMarkedAsSent::dispatch($order, null);

    $activities = Activity::where('order_id', $order->id)
        ->where('type', ActivityType::FILE)
        ->get();

    expect($activities)->toHaveCount(1)
        ->and($activities->first()->person_id)->toBeNull();
});

it('tracks per-person confirmation progress', function () {
    $person1 = Person::factory()->create();
    $person2 = Person::factory()->create();

    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach([$person1->id, $person2->id]);

    $order = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
        'combine_order' => false,
    ]);

    expect($order->allPersonsConfirmed())->toBeFalse();

    $progress = $order->confirmationProgress();
    expect($progress['confirmed'])->toBe(0)
        ->and($progress['total'])->toBe(2);

    OrderPersonConfirmation::create([
        'order_id'                    => $order->id,
        'person_id'                   => $person1->id,
        'confirmation_letter_content' => '<p>Letter for person 1</p>',
        'email_sent_at'               => now(),
    ]);

    $order->refresh();

    $progress = $order->confirmationProgress();
    expect($progress['confirmed'])->toBe(1)
        ->and($progress['total'])->toBe(2);

    expect($order->allPersonsConfirmed())->toBeFalse();

    OrderPersonConfirmation::create([
        'order_id'                    => $order->id,
        'person_id'                   => $person2->id,
        'confirmation_letter_content' => '<p>Letter for person 2</p>',
        'email_sent_at'               => now(),
    ]);

    $order->refresh();
    expect($order->allPersonsConfirmed())->toBeTrue();
});

it('scopeForPerson returns only person_id-assigned activities to that person', function () {
    config(['api.keys' => ['test-api-key']]);

    $keycloakUserId1 = (string) Str::uuid();
    $keycloakUserId2 = (string) Str::uuid();

    $person1 = Person::factory()->create([
        'keycloak_user_id' => $keycloakUserId1,
        'is_active'        => true,
    ]);
    $person2 = Person::factory()->create([
        'keycloak_user_id' => $keycloakUserId2,
        'is_active'        => true,
    ]);

    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach([$person1->id, $person2->id]);

    $order = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
        'combine_order' => false,
    ]);

    // Activity assigned to person1
    $activity1 = Activity::create([
        'type'      => ActivityType::FILE,
        'title'     => 'For person 1',
        'is_done'   => true,
        'order_id'  => $order->id,
        'person_id' => $person1->id,
    ]);
    $activity1->portalPersons()->attach($person1->id);

    // Activity assigned to person2
    $activity2 = Activity::create([
        'type'      => ActivityType::FILE,
        'title'     => 'For person 2',
        'is_done'   => true,
        'order_id'  => $order->id,
        'person_id' => $person2->id,
    ]);
    $activity2->portalPersons()->attach($person2->id);

    // Activity without person_id (shared)
    $activity3 = Activity::create([
        'type'     => ActivityType::FILE,
        'title'    => 'Shared',
        'is_done'  => true,
        'order_id' => $order->id,
    ]);
    $activity3->portalPersons()->attach([$person1->id, $person2->id]);

    $person1Activities = Activity::forPerson($person1)->pluck('id')->sort()->values();
    $person2Activities = Activity::forPerson($person2)->pluck('id')->sort()->values();

    expect($person1Activities->toArray())->toContain($activity1->id)
        ->and($person1Activities->toArray())->toContain($activity3->id)
        ->and($person1Activities->toArray())->not->toContain($activity2->id);

    expect($person2Activities->toArray())->toContain($activity2->id)
        ->and($person2Activities->toArray())->toContain($activity3->id)
        ->and($person2Activities->toArray())->not->toContain($activity1->id);
});

it('getPatientsFromActivity returns only direct person when person_id is set', function () {
    $person1 = Person::factory()->create();
    $person2 = Person::factory()->create();

    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach([$person1->id, $person2->id]);

    $order = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
        'combine_order' => false,
    ]);

    $activityWithPerson = Activity::create([
        'type'      => ActivityType::FILE,
        'title'     => 'Per-person',
        'is_done'   => true,
        'order_id'  => $order->id,
        'person_id' => $person1->id,
    ]);

    $patients = $activityWithPerson->getPatientsFromActivity();
    expect($patients)->toHaveCount(1)
        ->and($patients->first()->id)->toBe($person1->id);

    $activityShared = Activity::create([
        'type'     => ActivityType::FILE,
        'title'    => 'Shared',
        'is_done'  => true,
        'order_id' => $order->id,
    ]);

    $sharedPatients = $activityShared->getPatientsFromActivity();
    expect($sharedPatients)->toHaveCount(2);
});

it('per-person documents are only visible to assigned person via API', function () {
    config(['api.keys' => ['test-api-key']]);

    $keycloakUserId1 = (string) Str::uuid();
    $keycloakUserId2 = (string) Str::uuid();

    $person1 = Person::factory()->create([
        'keycloak_user_id' => $keycloakUserId1,
        'is_active'        => true,
    ]);
    $person2 = Person::factory()->create([
        'keycloak_user_id' => $keycloakUserId2,
        'is_active'        => true,
    ]);

    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach([$person1->id, $person2->id]);

    $order = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
        'combine_order' => false,
    ]);

    $activity = Activity::create([
        'type'       => ActivityType::FILE,
        'title'      => 'Orderbevestiging PDF – person 1',
        'is_done'    => true,
        'order_id'   => $order->id,
        'person_id'  => $person1->id,
        'additional' => ['document_type' => 'order_confirmation'],
    ]);
    $activity->portalPersons()->attach($person1->id);

    $activity->files()->create([
        'name' => 'test.pdf',
        'path' => 'activities/'.$activity->id.'/test.pdf',
    ]);

    // Person 1 should see their document
    $response1 = $this->getJson(
        "/api/patient/{$keycloakUserId1}/documents",
        ['X-API-KEY' => 'test-api-key']
    );

    $response1->assertOk();
    $response1->assertJsonPath('meta.total', 1);

    // Person 2 should NOT see person 1's document
    $response2 = $this->getJson(
        "/api/patient/{$keycloakUserId2}/documents",
        ['X-API-KEY' => 'test-api-key']
    );

    $response2->assertOk();
    $response2->assertJsonPath('meta.total', 0);
});
