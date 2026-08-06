<?php

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\AiFeedback;
use App\Models\Order;
use App\Models\OrderCheck;
use App\Models\OrderItem;
use App\Models\SalesLead;
use App\Services\Ai\AiSubjectRegistry;
use App\Services\Ai\Context\AiContextBuilder;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

function contextFor(string $subjectKey, $subject): array
{
    $builder = app(AiSubjectRegistry::class)->builder($subjectKey);

    return [$builder->build($subject), $builder];
}

function payloadFor(string $subjectKey, $subject): array
{
    [$context, $builder] = contextFor($subjectKey, $subject);

    return $builder->forLlm($context);
}

test('every registered subject resolves to a builder and a prompt', function () {
    $registry = app(AiSubjectRegistry::class);

    expect($registry->keys())->toEqualCanonicalizing(['leads', 'persons', 'orders', 'sales_leads']);

    foreach ($registry->all() as $key => $definition) {
        expect(config("ai_prompts.{$definition->useCase}.prompt"))
            ->toBeString()
            ->not->toBeEmpty()
            ->and($registry->builder($key))->toBeInstanceOf(AiContextBuilder::class)
            // The subject key doubles as the morph alias stored in ai_summaries.subject_type,
            // so a mismatch with the morph map would silently orphan every summary.
            ->and($definition->morphClass())->toBe($key)
            ->and($definition->payloadKey)->not->toBeEmpty();
    }
});

test('the person payload covers the whole relationship instead of one lead', function () {
    $person = Person::factory()->create();

    $first = Lead::factory()->create(['description' => 'Eerste MRI-traject']);
    $second = Lead::factory()->create(['description' => 'Tweede traject knie']);
    $first->persons()->attach($person);
    $second->persons()->attach($person);

    $firstSales = SalesLead::factory()->create(['lead_id' => $first->id, 'name' => 'MRI rug']);
    $executed = Order::factory()->create([
        'sales_lead_id'        => $firstSales->id,
        'title'                => 'MRI rug',
        'total_price'          => 500,
        'first_examination_at' => now()->subMonths(6),
    ]);

    $secondSales = SalesLead::factory()->create(['lead_id' => $second->id, 'name' => 'MRI knie']);
    $upcoming = Order::factory()->create([
        'sales_lead_id'        => $secondSales->id,
        'title'                => 'MRI knie',
        'total_price'          => 750,
        'first_examination_at' => now()->addWeeks(2),
    ]);

    Activity::create([
        'type'         => ActivityType::CALL->value,
        'title'        => 'Gebeld over vervolgonderzoek',
        'comment'      => 'Patiente overweegt een controle na de knie-scan.',
        'is_done'      => true,
        'status'       => ActivityStatus::DONE->value,
        'lead_id'      => $second->id,
        'completed_at' => now()->subDays(3),
    ]);

    $payload = payloadFor('persons', $person);

    expect($payload['person']['ref'])->toBe("person:{$person->id}")
        ->and($payload['person'])->not->toHaveKey('id')
        ->and($payload['relationship']['order_count'])->toBe(2)
        ->and($payload['relationship']['lead_count'])->toBe(2)
        ->and($payload['relationship']['lifetime_value'])->toBe(500.0)
        ->and(collect($payload['upcoming_orders'])->pluck('ref')->all())
        ->toBe(["order:{$upcoming->id}:examination"])
        // Executed trajectories belong in history, not in the "still ahead" block.
        ->and(collect($payload['history'])->pluck('ref')->all())
        ->toBe(["order:{$executed->id}:examination"])
        ->and($payload)->not->toHaveKey('current_order')
        ->and(collect($payload['timeline'])->pluck('text')->implode(' '))
        ->toContain('vervolgonderzoek');
});

test('the order payload carries operational status, items and open checks', function () {
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id, 'name' => 'MRI wervelkolom']);
    $order = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'order_number'         => 'ORD-900',
        'title'                => 'MRI wervelkolom',
        'total_price'          => 1200,
        'first_examination_at' => now()->addDays(5),
    ]);
    $sibling = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'order_number'         => 'ORD-800',
        'title'                => 'Eerdere echo',
        'first_examination_at' => now()->subMonths(2),
    ]);

    OrderItem::factory()->create([
        'order_id'    => $order->id,
        'name'        => 'MRI wervelkolom compleet',
        'quantity'    => 1,
        'total_price' => 1200,
    ]);
    OrderCheck::create(['order_id' => $order->id, 'name' => 'Vragenlijst ontvangen', 'done' => false]);
    OrderCheck::create(['order_id' => $order->id, 'name' => 'Betaling binnen', 'done' => true]);

    $payload = payloadFor('orders', $order);

    expect($payload['order']['number'])->toBe('ORD-900')
        ->and($payload['order']['ref'])->toBe("order:{$order->id}:examination")
        ->and($payload['order']['days_until_exam'])->toBe(5)
        ->and($payload['order']['payment_status'])->toBeString()
        ->and($payload)->not->toHaveKey('current_order')
        ->and(collect($payload['order_items'])->pluck('name')->all())->toContain('MRI wervelkolom compleet')
        ->and($payload['open_checks'])->toBe(['Vragenlijst ontvangen'])
        ->and(collect($payload['history'])->pluck('ref')->all())
        ->toContain("order:{$sibling->id}:examination");
});

test('the sales lead payload lists all of its orders and keeps earlier trajectories as history', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $lead->persons()->attach($person);

    $earlierLead = Lead::factory()->create(['description' => 'Eerder afgerond traject']);
    $earlierLead->persons()->attach($person);
    $earlierSales = SalesLead::factory()->create(['lead_id' => $earlierLead->id, 'name' => 'Oud traject']);
    $earlierOrder = Order::factory()->create([
        'sales_lead_id'        => $earlierSales->id,
        'title'                => 'Oude scan',
        'first_examination_at' => now()->subYear(),
    ]);

    $salesLead = SalesLead::factory()->create([
        'lead_id'     => $lead->id,
        'name'        => 'Preventief onderzoek',
        'description' => 'Volledig preventief pakket',
    ]);
    $salesLead->persons()->attach($person);

    $first = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
        'title'         => 'Bodyscan',
        'total_price'   => 900,
    ]);
    $second = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
        'title'         => 'Hartscan',
        'total_price'   => 600,
    ]);

    $payload = payloadFor('sales_leads', $salesLead);

    expect($payload['sales_lead']['name'])->toBe('Preventief onderzoek')
        ->and($payload['sales_lead']['total_value'])->toBe(1500.0)
        ->and($payload['sales_lead']['order_count'])->toBe(2)
        ->and(collect($payload['orders'])->pluck('ref')->all())
        ->toContain("order:{$first->id}:created")
        ->toContain("order:{$second->id}:created")
        ->and(collect($payload['history'])->pluck('ref')->all())
        ->toContain("order:{$earlierOrder->id}:examination");
});

test('feedback and citation sources are subject specific', function () {
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'first_examination_at' => now()->addDays(3),
    ]);

    AiFeedback::factory()->forSubject($order)->create(['feedback' => 'Correctie op de order.']);
    AiFeedback::factory()->forSubject($lead)->create(['feedback' => 'Correctie op de lead.']);

    $orderPayload = payloadFor('orders', $order);
    [$orderContext] = contextFor('orders', $order);

    expect(collect($orderPayload['feedback'])->pluck('text')->all())
        ->toBe(['Correctie op de order.'])
        ->and(collect($orderContext['sources'])->pluck('ref')->all())
        ->toContain("order:{$order->id}:examination");
});

test('a person without any history still produces a usable payload', function () {
    $person = Person::factory()->create();

    $payload = payloadFor('persons', $person);

    expect($payload)->toHaveKey('person')
        ->and($payload['person']['ref'])->toBe("person:{$person->id}")
        ->and($payload)->not->toHaveKey('history')
        ->and($payload)->not->toHaveKey('timeline');
});
