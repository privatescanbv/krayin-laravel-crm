<?php

use App\Enums\ActivityType;
use App\Models\Order;
use App\Models\SalesLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Activity\Models\Activity;
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Lead;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = makeUser();
    $this->actingAs($this->user, 'user');
});

test('lead open activities endpoint excludes email activity items', function () {
    $lead = Lead::factory()->create();

    Activity::create([
        'type'          => ActivityType::TASK->value,
        'title'         => 'Lead open task',
        'lead_id'       => $lead->id,
        'is_done'       => 0,
        'user_id'       => $this->user->id,
        'schedule_from' => now(),
        'schedule_to'   => now()->addHour(),
    ]);

    Email::create([
        'subject'  => 'Lead email item',
        'reply'    => '<p>Email inhoud</p>',
        'from'     => json_encode(['address' => 'sender@example.com', 'name' => 'Sender']),
        'reply_to' => json_encode(['patient@example.com']),
        'lead_id'  => $lead->id,
        'source'   => 'system',
    ]);

    $response = $this->getJson(route('admin.leads.activities.index', $lead->id).'?is_done=0');

    $response->assertOk();

    $types = collect($response->json('data'))->pluck('type');
    $titles = collect($response->json('data'))->pluck('title');

    expect($types)->not->toContain('email')
        ->and($titles)->toContain('Lead open task')
        ->and($titles)->not->toContain('Lead email item');
});

test('sales lead open activities endpoint excludes email activity items', function () {
    $salesLead = SalesLead::factory()->create();

    Activity::create([
        'type'          => ActivityType::TASK->value,
        'title'         => 'Sales open task',
        'sales_lead_id' => $salesLead->id,
        'is_done'       => 0,
        'user_id'       => $this->user->id,
        'schedule_from' => now(),
        'schedule_to'   => now()->addHour(),
    ]);

    Email::create([
        'subject'       => 'Sales email item',
        'reply'         => '<p>Email inhoud</p>',
        'from'          => json_encode(['address' => 'sender@example.com', 'name' => 'Sender']),
        'reply_to'      => json_encode(['patient@example.com']),
        'sales_lead_id' => $salesLead->id,
        'source'        => 'system',
    ]);

    $response = $this->getJson(
        route('admin.sales-leads.activities.index', $salesLead->id).'?is_done=0&hierarchy=false'
    );

    $response->assertOk();

    $types = collect($response->json('data'))->pluck('type');
    $titles = collect($response->json('data'))->pluck('title');

    expect($types)->not->toContain('email')
        ->and($titles)->toContain('Sales open task')
        ->and($titles)->not->toContain('Sales email item');
});

test('order open activities endpoint excludes email activity items', function () {
    $order = Order::factory()->create();

    Activity::create([
        'type'          => ActivityType::TASK->value,
        'title'         => 'Order open task',
        'order_id'      => $order->id,
        'is_done'       => 0,
        'user_id'       => $this->user->id,
        'schedule_from' => now(),
        'schedule_to'   => now()->addHour(),
    ]);

    Email::create([
        'subject'       => 'Order email item',
        'reply'         => '<p>Email inhoud</p>',
        'from'          => json_encode(['address' => 'sender@example.com', 'name' => 'Sender']),
        'reply_to'      => json_encode(['patient@example.com']),
        'order_id'      => $order->id,
        'sales_lead_id' => $order->sales_lead_id,
        'source'        => 'system',
    ]);

    $response = $this->getJson(route('admin.orders.activities.index', $order->id).'?is_done=0');

    $response->assertOk();

    $types = collect($response->json('data'))->pluck('type');
    $titles = collect($response->json('data'))->pluck('title');

    expect($types)->not->toContain('email')
        ->and($titles)->toContain('Order open task')
        ->and($titles)->not->toContain('Order email item');
});
