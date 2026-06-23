<?php

use App\Enums\ActivityActionType;
use App\Enums\ActivityType;
use App\Models\ActivityAction;
use App\Models\Order;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->actingAs(getDefaultAdmin(), 'user');
});

function makeActivity(array $attrs): Activity
{
    return Activity::create(array_merge([
        'type'          => ActivityType::TASK->value,
        'title'         => 'Test activity',
        'comment'       => null,
        'is_done'       => 0,
        'user_id'       => getDefaultAdmin()->id,
        'schedule_from' => now(),
        'schedule_to'   => now()->addDay(),
    ], $attrs));
}

function makeLeadWithStage(): Lead
{
    $pipeline = Pipeline::factory()->create();
    $stage = Stage::create([
        'name'             => 'Stage',
        'lead_pipeline_id' => $pipeline->id,
        'code'             => 'stage-'.uniqid(),
        'sort_order'       => 1,
        'is_won'           => false,
        'is_lost'          => false,
    ]);

    return Lead::factory()->create([
        'lead_pipeline_id'       => $pipeline->id,
        'lead_pipeline_stage_id' => $stage->id,
    ]);
}

// ── Order ──────────────────────────────────────────────────────────────────

test('order activities endpoint only returns own activities', function () {
    $salesLead = SalesLead::factory()->create();
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    makeActivity(['order_id' => $order->id, 'title' => 'Own order activity']);
    makeActivity(['sales_lead_id' => $salesLead->id, 'title' => 'SalesLead activity']);

    $response = $this->getJson(route('admin.orders.activities.index', $order->id));
    $response->assertOk();

    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('Own order activity')
        ->and($titles)->not->toContain('SalesLead activity');
});

test('order own activity has no entity_source label', function () {
    $salesLead = SalesLead::factory()->create();
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);
    makeActivity(['order_id' => $order->id, 'title' => 'Own']);

    $response = $this->getJson(route('admin.orders.activities.index', $order->id));
    $response->assertOk();

    $item = collect($response->json('data'))->firstWhere('title', 'Own');
    expect($item['entity_source'])->toBeNull();
});

// ── SalesLead ──────────────────────────────────────────────────────────────

test('saleslead activities endpoint returns own and child order activities', function () {
    $salesLead = SalesLead::factory()->create();
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    makeActivity(['sales_lead_id' => $salesLead->id, 'title' => 'Sales own']);
    makeActivity(['order_id' => $order->id, 'title' => 'Order child']);

    $response = $this->getJson(route('admin.sales-leads.activities.index', $salesLead->id));
    $response->assertOk();

    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('Sales own')
        ->and($titles)->toContain('Order child');
});

test('saleslead own activity has no entity_source label', function () {
    $salesLead = SalesLead::factory()->create();
    makeActivity(['sales_lead_id' => $salesLead->id, 'title' => 'Sales own']);

    $response = $this->getJson(route('admin.sales-leads.activities.index', $salesLead->id));
    $response->assertOk();

    $item = collect($response->json('data'))->firstWhere('title', 'Sales own');
    expect($item['entity_source'])->toBeNull();
});

test('saleslead child order activity has entity_source with type order and url', function () {
    $salesLead = SalesLead::factory()->create();
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id, 'title' => 'Test Order']);
    makeActivity(['order_id' => $order->id, 'title' => 'Order child']);

    $response = $this->getJson(route('admin.sales-leads.activities.index', $salesLead->id));
    $response->assertOk();

    $item = collect($response->json('data'))->firstWhere('title', 'Order child');
    expect($item['entity_source']['type'])->toBe('order')
        ->and($item['entity_source']['label'])->toStartWith('Order:')
        ->and($item['entity_source']['url'])->toContain((string) $order->id);
});

test('saleslead completed activities are ordered by completed date', function () {
    $salesLead = SalesLead::factory()->create();

    makeActivity([
        'sales_lead_id' => $salesLead->id,
        'title'         => 'Older completed activity',
        'is_done'       => 1,
        'completed_at'  => now()->subDays(3),
    ]);

    makeActivity([
        'sales_lead_id' => $salesLead->id,
        'title'         => 'Newest completed activity',
        'is_done'       => 1,
        'completed_at'  => now()->subHour(),
    ]);

    $response = $this->getJson(route('admin.sales-leads.activities.index', $salesLead->id));
    $response->assertOk();

    expect(collect($response->json('data'))->pluck('title')->take(2)->all())
        ->toBe([
            'Newest completed activity',
            'Older completed activity',
        ]);
});

test('activity resource keeps full action labels for browser truncation', function () {
    $salesLead = SalesLead::factory()->create();
    $activity = makeActivity([
        'sales_lead_id' => $salesLead->id,
        'type'          => ActivityType::TASK->value,
        'title'         => 'Task with long action',
    ]);

    $body = 'Wij gaan de operatie inplannen voor 9 juli en publiceren daarna alle praktische informatie voor de patient.';
    ActivityAction::create([
        'activity_id' => $activity->id,
        'type'        => ActivityActionType::Notitie->value,
        'body'        => $body,
        'created_by'  => getDefaultAdmin()->id,
    ]);

    $response = $this->getJson(route('admin.sales-leads.activities.index', $salesLead->id));
    $response->assertOk();

    $item = collect($response->json('data'))->firstWhere('title', 'Task with long action');

    expect($item['actions'][0]['label'])->toBe($body);
});

// ── Lead ───────────────────────────────────────────────────────────────────

test('lead activities endpoint only returns own activities', function () {
    $lead = makeLeadWithStage();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    makeActivity(['lead_id' => $lead->id, 'title' => 'Lead own']);
    makeActivity(['sales_lead_id' => $salesLead->id, 'title' => 'Sales child']);
    makeActivity(['order_id' => $order->id, 'title' => 'Order grandchild']);

    $response = $this->getJson(route('admin.leads.activities.index', $lead->id));
    $response->assertOk();

    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('Lead own')
        ->and($titles)->not->toContain('Sales child')
        ->and($titles)->not->toContain('Order grandchild');
});

test('lead own activity has no entity_source label', function () {
    $lead = makeLeadWithStage();
    makeActivity(['lead_id' => $lead->id, 'title' => 'Lead own']);

    $response = $this->getJson(route('admin.leads.activities.index', $lead->id));
    $response->assertOk();

    $item = collect($response->json('data'))->firstWhere('title', 'Lead own');
    expect($item['entity_source'])->toBeNull();
});

// ── Person ─────────────────────────────────────────────────────────────────

test('person own activity has no entity_source label', function () {
    $person = Person::factory()->create();
    makeActivity(['person_id' => $person->id, 'title' => 'Person own']);

    $response = $this->getJson(route('admin.contacts.persons.activities.index', $person->id));
    $response->assertOk();

    $item = collect($response->json('data'))->firstWhere('title', 'Person own');
    expect($item['entity_source'])->toBeNull();
});

test('person sees lead activity with entity_source type lead and url', function () {
    $person = Person::factory()->create();
    $lead = makeLeadWithStage();
    $lead->attachPersons([$person->id]);
    makeActivity(['lead_id' => $lead->id, 'title' => 'Lead child']);

    $response = $this->getJson(route('admin.contacts.persons.activities.index', $person->id));
    $response->assertOk();

    $item = collect($response->json('data'))->firstWhere('title', 'Lead child');
    expect($item)->not->toBeNull()
        ->and($item['entity_source']['type'])->toBe('lead')
        ->and($item['entity_source']['label'])->toStartWith('Lead:')
        ->and($item['entity_source']['url'])->toContain((string) $lead->id);
});

test('person sees saleslead activity with entity_source type sales and url', function () {
    $person = Person::factory()->create();
    $salesLead = SalesLead::factory()->create();
    DB::table('saleslead_persons')->insert([
        'saleslead_id' => $salesLead->id,
        'person_id'    => $person->id,
    ]);
    makeActivity(['sales_lead_id' => $salesLead->id, 'title' => 'Sales child']);

    $response = $this->getJson(route('admin.contacts.persons.activities.index', $person->id));
    $response->assertOk();

    $item = collect($response->json('data'))->firstWhere('title', 'Sales child');
    expect($item)->not->toBeNull()
        ->and($item['entity_source']['type'])->toBe('sales')
        ->and($item['entity_source']['url'])->toContain((string) $salesLead->id);
});

test('person does not see activities from unrelated entities', function () {
    $person = Person::factory()->create();
    $salesLead = SalesLead::factory()->create();
    makeActivity(['person_id' => $person->id, 'title' => 'Person own']);
    makeActivity(['sales_lead_id' => $salesLead->id, 'title' => 'Unrelated sales']);

    $response = $this->getJson(route('admin.contacts.persons.activities.index', $person->id));
    $response->assertOk();

    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('Person own')
        ->and($titles)->not->toContain('Unrelated sales');
});

test('activity view redirects to edit preserving return_url', function () {
    $lead = makeLeadWithStage();
    $activity = makeActivity(['lead_id' => $lead->id]);
    $returnUrl = '/admin/leads/view/'.$lead->id.'#activiteiten';

    $response = $this->get(
        route('admin.activities.view', $activity->id).'?return_url='.urlencode($returnUrl)
    );

    $response->assertRedirect(
        route('admin.activities.edit', $activity->id).'?return_url='.urlencode($returnUrl)
    );
});
