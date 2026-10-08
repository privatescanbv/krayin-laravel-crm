<?php

use App\Enums\ActivityType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SalesLead;
use App\Services\Ai\AiSubjectRegistry;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;

/**
 * @return array{0: array<string, mixed>, 1: array<string, mixed>} [llm payload, audit snapshot]
 */
function aiHistoryPayload(string $subjectKey, $subject): array
{
    $builder = app(AiSubjectRegistry::class)->builder($subjectKey);
    $context = $builder->build($subject);

    return [$builder->forLlm($context), $builder->auditSnapshot($context)];
}

function aiHistoryStage(string $state): int
{
    return Stage::factory()->{$state}()->create(['lead_pipeline_id' => Pipeline::factory()->create()->id])->id;
}

function aiHistoryLead(Person $person, array $attributes = []): Lead
{
    $lead = Lead::factory()->create($attributes);
    $lead->persons()->attach($person);

    return $lead;
}

function aiHistoryPurchase(Person $person, string $closedAt): Order
{
    $order = Order::factory()->create([
        'pipeline_stage_id' => aiHistoryStage('won'),
        'closed_at'         => $closedAt,
    ]);

    OrderItem::factory()->create(['order_id' => $order->id, 'person_id' => $person->id, 'total_price' => 500]);

    return $order;
}

test('a lead of a returning non-buyer carries the history as it stood when the lead came in', function () {
    $person = Person::factory()->create();
    $earlier = aiHistoryLead($person, ['lead_pipeline_stage_id' => aiHistoryStage('lost'), 'created_at' => now()->subMonths(6)]);
    $call = Activity::create(['type' => ActivityType::CALL->value, 'title' => 'Gebeld', 'lead_id' => $earlier->id]);
    $call->forceFill(['created_at' => now()->subMonths(5)])->save();
    $current = aiHistoryLead($person);

    [$payload, $audit] = aiHistoryPayload('leads', $current);

    expect($payload['customer_history'])->toBe([
        'customer_type'              => 'Bestaande klant, nooit gekocht',
        'prior_lead_count'           => 1,
        'prior_lost_lead_count'      => 1,
        'prior_sales_activity_count' => 1,
    ])->and($audit['customer_history'])->toBe($payload['customer_history']);
});

test('a lead of an earlier buyer carries the purchase count and date', function () {
    $person = Person::factory()->create();
    aiHistoryLead($person, ['created_at' => now()->subYear()]);
    aiHistoryPurchase($person, now()->subMonths(8)->toDateString());
    $current = aiHistoryLead($person);

    [$payload] = aiHistoryPayload('leads', $current);

    expect($payload['customer_history'])
        ->toMatchArray([
            'customer_type'        => 'Bestaande klant, eerder gekocht',
            'prior_lead_count'     => 1,
            'prior_purchase_count' => 1,
            'last_purchase_at'     => now()->subMonths(8)->toDateString(),
        ]);
});

test('a lead of a new patient has no customer history block', function () {
    [$payload, $audit] = aiHistoryPayload('leads', aiHistoryLead(Person::factory()->create()));

    expect($payload)->not->toHaveKey('customer_history')
        ->and($audit['customer_history'])->toBe([]);
});

test('a sales lead takes the customer history of its lead', function () {
    $person = Person::factory()->create();
    aiHistoryLead($person, ['created_at' => now()->subMonths(3)]);
    $current = aiHistoryLead($person);
    $salesLead = SalesLead::factory()->create(['lead_id' => $current->id]);

    [$payload] = aiHistoryPayload('sales_leads', $salesLead);

    expect($payload['customer_history'])->toMatchArray([
        'customer_type'    => 'Bestaande klant, nooit gekocht',
        'prior_lead_count' => 1,
    ]);
});

test('an order knows which purchase it is and how long ago the previous one was', function () {
    $person = Person::factory()->create();
    $first = aiHistoryPurchase($person, now()->subMonths(20)->toDateString());
    $second = aiHistoryPurchase($person, now()->subMonths(8)->toDateString());
    $third = aiHistoryPurchase($person, now()->toDateString());

    expect(aiHistoryPayload('orders', $first)[0]['customer_history'])->toBe(['purchase_sequence' => 1])
        ->and(aiHistoryPayload('orders', $second)[0]['customer_history'])->toBe([
            'purchase_sequence'              => 2,
            'previous_purchase_at'           => now()->subMonths(20)->toDateString(),
            'months_since_previous_purchase' => 12,
        ])
        ->and(aiHistoryPayload('orders', $third)[0]['customer_history'])->toBe([
            'purchase_sequence'              => 3,
            'previous_purchase_at'           => now()->subMonths(8)->toDateString(),
            'months_since_previous_purchase' => 8,
        ]);
});

test('a person carries the current customer type with lost leads, sales effort and revenue', function () {
    $person = Person::factory()->create();
    aiHistoryLead($person, ['lead_pipeline_stage_id' => aiHistoryStage('lost')]);
    aiHistoryPurchase($person, now()->subMonth()->toDateString());

    [$payload] = aiHistoryPayload('persons', $person);

    expect($payload['customer_history'])->toBe([
        'customer_type'   => 'Bestaande klant, eerder gekocht',
        'lost_lead_count' => 1,
        'revenue_total'   => 500.0,
    ]);
});

test('a person without history has no customer history block', function () {
    [$payload] = aiHistoryPayload('persons', Person::factory()->create());

    expect($payload)->not->toHaveKey('customer_history');
});
