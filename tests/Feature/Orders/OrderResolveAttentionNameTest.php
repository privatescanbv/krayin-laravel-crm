<?php

use App\Models\Order;
use App\Models\SalesLead;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;

test('resolveAttentionName returns organization name for business order', function () {
    $org = Organization::factory()->create(['name' => 'Acme BV']);

    $salesLead = SalesLead::factory()->create();
    $order = Order::factory()->create([
        'sales_lead_id'   => $salesLead->id,
        'is_business'     => true,
        'organization_id' => $org->id,
    ]);

    expect($order->resolveAttentionName())->toBe('Acme BV');
});

test('resolveAttentionName returns placeholder when business order has no organization', function () {
    $salesLead = SalesLead::factory()->create();
    $order = Order::factory()->create([
        'sales_lead_id'   => $salesLead->id,
        'is_business'     => true,
        'organization_id' => null,
    ]);

    expect($order->resolveAttentionName())->toBe('[Organisatie heeft geen naam]');
});

test('resolveAttentionName returns contact person name for non-business order', function () {
    $person = Person::factory()->create(['first_name' => 'Jan', 'last_name' => 'Jansen']);

    $salesLead = SalesLead::factory()->create();
    $salesLead->persons()->attach($person->id);

    $order = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
        'is_business'   => false,
    ]);

    expect($order->resolveAttentionName())->toBe($person->name);
});

test('resolveAttentionName returns empty string when non-business order has no persons', function () {
    $salesLead = SalesLead::factory()->create();
    $order = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
        'is_business'   => false,
    ]);

    expect($order->resolveAttentionName())->toBe('');
});
