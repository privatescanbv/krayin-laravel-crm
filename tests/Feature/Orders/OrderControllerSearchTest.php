<?php

use App\Models\Order;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    $this->user = User::factory()->create(['first_name' => 'Admin', 'last_name' => 'Tester']);
    $this->actingAs($this->user, 'user');
    $this->withoutMiddleware(Authenticate::class);

    $this->stage = Stage::first();
    if (! $this->stage) {
        $pipeline = Pipeline::first() ?? Pipeline::create([
            'name'        => 'Default Pipeline',
            'is_default'  => 1,
            'rotten_days' => 30,
        ]);
        $this->stage = Stage::create([
            'name'             => 'New',
            'code'             => 'new',
            'lead_pipeline_id' => $pipeline->id,
            'sort_order'       => 1,
        ]);
    }
});

test('order search by order_number returns matching order', function () {
    $salesLead = SalesLead::factory()->create(['user_id' => $this->user->id]);

    $order = Order::factory()->create([
        'order_number'      => '202600042',
        'title'             => 'Test operatie',
        'pipeline_stage_id' => $this->stage->id,
        'sales_lead_id'     => $salesLead->id,
        'user_id'           => $this->user->id,
    ]);

    $response = $this->getJson(route('admin.orders.search', [
        'search'       => 'order_number:202600042;title:202600042;',
        'searchFields' => 'order_number:like;title:like;',
        'searchJoin'   => 'or',
        'limit'        => 15,
    ]));

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($order->id);
});

test('order search by title returns matching order', function () {
    $salesLead = SalesLead::factory()->create(['user_id' => $this->user->id]);

    $order = Order::factory()->create([
        'title'             => 'Hernia operatie',
        'pipeline_stage_id' => $this->stage->id,
        'sales_lead_id'     => $salesLead->id,
        'user_id'           => $this->user->id,
    ]);

    $response = $this->getJson(route('admin.orders.search', [
        'search'       => 'order_number:Hernia;title:Hernia;',
        'searchFields' => 'order_number:like;title:like;',
        'searchJoin'   => 'or',
        'limit'        => 15,
    ]));

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($order->id);
});

test('order search does not return non-matching orders', function () {
    $salesLead = SalesLead::factory()->create(['user_id' => $this->user->id]);

    $order = Order::factory()->create([
        'order_number'      => '202699999',
        'title'             => 'Ongerelateerde operatie',
        'pipeline_stage_id' => $this->stage->id,
        'sales_lead_id'     => $salesLead->id,
        'user_id'           => $this->user->id,
    ]);

    $response = $this->getJson(route('admin.orders.search', [
        'search'       => 'order_number:202600042;title:202600042;',
        'searchFields' => 'order_number:like;title:like;',
        'searchJoin'   => 'or',
        'limit'        => 15,
    ]));

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->not->toContain($order->id);
});

test('order search returns 200 with empty data when nothing matches', function () {
    $response = $this->getJson(route('admin.orders.search', [
        'search'       => 'order_number:000000000;title:000000000;',
        'searchFields' => 'order_number:like;title:like;',
        'searchJoin'   => 'or',
        'limit'        => 15,
    ]));

    $response->assertOk();
    expect($response->json('data'))->toBeArray();
});

test('order search rejects invalid fields', function () {
    $response = $this->getJson(route('admin.orders.search', [
        'search'       => 'emails:test@example.com;',
        'searchFields' => 'emails:like;',
        'limit'        => 10,
    ]));

    $response->assertBadRequest();
});

test('order search response contains expected fields', function () {
    $salesLead = SalesLead::factory()->create(['user_id' => $this->user->id]);

    $order = Order::factory()->create([
        'order_number'      => '202600100',
        'title'             => 'Veld controle operatie',
        'pipeline_stage_id' => $this->stage->id,
        'sales_lead_id'     => $salesLead->id,
        'user_id'           => $this->user->id,
    ]);

    $response = $this->getJson(route('admin.orders.search', [
        'search'       => 'order_number:202600100;title:202600100;',
        'searchFields' => 'order_number:like;title:like;',
        'searchJoin'   => 'or',
        'limit'        => 15,
    ]));

    $response->assertOk();
    $data = collect($response->json('data'))->firstWhere('id', $order->id);
    expect($data)->not->toBeNull();
    expect($data)->toHaveKeys(['id', 'order_number', 'title', 'pipeline_stage_id', 'sales_lead_id']);
});
