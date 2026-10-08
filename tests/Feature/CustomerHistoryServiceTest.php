<?php

use App\Enums\ActivityType;
use App\Enums\CallStatus as CallStatusEnum;
use App\Enums\CustomerType;
use App\Enums\OrderItemStatus;
use App\Models\CallStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SalesLead;
use App\Services\CustomerHistory\CustomerHistoryService;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;

function chStage(string $state): int
{
    return Stage::factory()->{$state}()->create(['lead_pipeline_id' => Pipeline::factory()->create()->id])->id;
}

function chHistory(Person $person, ...$args)
{
    return app(CustomerHistoryService::class)->forPerson($person, ...$args);
}

function chLeadFor(Person $person, array $attributes = []): Lead
{
    $lead = Lead::factory()->create($attributes);
    $lead->persons()->attach($person);

    return $lead;
}

function chPurchaseFor(Person $person, array $orderAttributes = [], array $itemAttributes = []): Order
{
    $order = Order::factory()->create([
        'pipeline_stage_id' => chStage('won'),
        ...$orderAttributes,
    ]);

    OrderItem::factory()->create([
        'order_id'    => $order->id,
        'person_id'   => $person->id,
        'total_price' => 500,
        ...$itemAttributes,
    ]);

    return $order;
}

test('a person without leads or orders is new', function () {
    $history = chHistory(Person::factory()->create());

    expect($history->customerType)->toBe(CustomerType::New)
        ->and($history->priorLeadCount)->toBe(0)
        ->and($history->purchaseCount)->toBe(0)
        ->and($history->salesActivityCount)->toBe(0);
});

test('a person with an earlier lead but no won order is an existing non-buyer', function () {
    $person = Person::factory()->create();
    chLeadFor($person, ['lead_pipeline_stage_id' => chStage('lost')]);
    $current = chLeadFor($person);

    $history = chHistory($person, excludeLeadId: $current->id);

    expect($history->customerType)->toBe(CustomerType::ExistingNonBuyer)
        ->and($history->priorLeadCount)->toBe(1)
        ->and($history->priorLostLeadCount)->toBe(1);
});

test('the lead being classified is not its own prior lead', function () {
    $person = Person::factory()->create();
    $current = chLeadFor($person);

    expect(chHistory($person, excludeLeadId: $current->id)->customerType)->toBe(CustomerType::New);
});

test('a lead linked through contact_person_id counts as a prior lead', function () {
    $person = Person::factory()->create();
    Lead::factory()->create(['contact_person_id' => $person->id]);

    expect(chHistory($person)->priorLeadCount)->toBe(1);
});

test('a won order before the reference moment makes the person an existing buyer', function () {
    $person = Person::factory()->create();
    chPurchaseFor($person, ['closed_at' => '2026-01-15']);
    chPurchaseFor($person, ['closed_at' => '2026-05-01'], ['total_price' => 250]);

    $history = chHistory($person, now()->setDate(2026, 6, 1));

    expect($history->customerType)->toBe(CustomerType::ExistingBuyer)
        ->and($history->purchaseCount)->toBe(2)
        ->and($history->firstPurchaseAt->toDateString())->toBe('2026-01-15')
        ->and($history->lastPurchaseAt->toDateString())->toBe('2026-05-01')
        ->and($history->revenueTotal)->toBe(750.0);
});

test('a purchase after the reference moment does not count', function () {
    $person = Person::factory()->create();
    chPurchaseFor($person, ['closed_at' => '2026-07-01']);

    expect(chHistory($person, now()->setDate(2026, 6, 1))->customerType)->toBe(CustomerType::New);
});

test('lost orders and lost order items are not purchases', function () {
    $person = Person::factory()->create();
    chPurchaseFor($person, ['pipeline_stage_id' => chStage('lost')]);
    chPurchaseFor($person, itemAttributes: ['status' => OrderItemStatus::LOST->value]);

    expect(chHistory($person)->purchaseCount)->toBe(0);
});

test('an order counts for the person on the order item, not for someone else', function () {
    $buyer = Person::factory()->create();
    $partner = Person::factory()->create();
    chPurchaseFor($buyer);

    expect(chHistory($buyer)->customerType)->toBe(CustomerType::ExistingBuyer)
        ->and(chHistory($partner)->customerType)->toBe(CustomerType::New);
});

test('soft-deleted leads are not prior leads', function () {
    $person = Person::factory()->create();
    chLeadFor($person)->delete();

    expect(chHistory($person)->priorLeadCount)->toBe(0);
});

test('sales activities on prior leads and their sales leads are counted', function () {
    $person = Person::factory()->create();
    $prior = chLeadFor($person);
    $salesLead = SalesLead::factory()->create(['lead_id' => $prior->id]);

    Activity::create(['type' => ActivityType::CALL->value, 'title' => 'Bellen', 'lead_id' => $prior->id]);
    Activity::create(['type' => ActivityType::TASK->value, 'title' => 'Offerte', 'sales_lead_id' => $salesLead->id]);
    Activity::create(['type' => ActivityType::SYSTEM->value, 'title' => 'Fase gewijzigd', 'lead_id' => $prior->id]);
    Email::create(['subject' => 'Info', 'name' => 'Wij', 'user_type' => 'user', 'reply' => 'x', 'lead_id' => $prior->id]);
    Email::create(['subject' => 'Re: Info', 'name' => 'Patient', 'user_type' => 'person', 'reply' => 'x', 'lead_id' => $prior->id]);

    expect(chHistory($person)->salesActivityCount)->toBe(3);
});

test('a lead only had contact when a call was spoken or the person e-mailed', function () {
    $service = app(CustomerHistoryService::class);
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);

    $call = Activity::create(['type' => ActivityType::CALL->value, 'title' => 'Bellen', 'lead_id' => $lead->id]);
    CallStatus::create(['activity_id' => $call->id, 'status' => CallStatusEnum::NOT_REACHABLE->value]);
    CallStatus::create(['activity_id' => $call->id, 'status' => CallStatusEnum::VOICEMAIL_LEFT->value]);

    expect($service->hadContact($lead))->toBeFalse();

    $spoken = Activity::create(['type' => ActivityType::CALL->value, 'title' => 'Bellen', 'sales_lead_id' => $salesLead->id]);
    CallStatus::create(['activity_id' => $spoken->id, 'status' => CallStatusEnum::SPOKEN->value]);

    expect($service->hadContact($lead))->toBeTrue();
});

test('an e-mail from the person counts as contact', function () {
    $lead = Lead::factory()->create();
    Email::create(['subject' => 'Vraag', 'name' => 'Patient', 'user_type' => 'person', 'reply' => 'x', 'lead_id' => $lead->id]);

    expect(app(CustomerHistoryService::class)->hadContact($lead))->toBeTrue();
});
