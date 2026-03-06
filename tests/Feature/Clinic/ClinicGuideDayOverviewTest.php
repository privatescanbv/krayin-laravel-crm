<?php

namespace Tests\Feature;

use App\Enums\PipelineStage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SalesLead;
use Carbon\Carbon;
use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Person;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Product\Models\Product;

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);

    $this->seed(TestSeeder::class);

    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('clinic guide get returns orders for given date', function () {
    $targetDate = '2026-03-15';

    $salesLead = SalesLead::factory()->create();

    $orderOnDate = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at' => Carbon::parse($targetDate)->setHour(10)->setMinute(30),
    ]);

    $orderOtherDate = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at' => Carbon::parse('2026-03-16')->setHour(14)->setMinute(0),
    ]);

    $response = $this->getJson(route('admin.clinic-guide.get', ['date' => $targetDate]));

    $response->assertOk();
    $response->assertJsonPath('date', $targetDate);
    $response->assertJsonPath('count', 1);

    $orderIds = collect($response->json('orders'))->pluck('order.id')->all();
    expect($orderIds)->toContain($orderOnDate->id)
        ->and($orderIds)->not->toContain($orderOtherDate->id);
});

test('clinic guide get filters out orders from other days', function () {
    $salesLead = SalesLead::factory()->create();

    Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at' => Carbon::parse('2026-04-01 09:00:00'),
    ]);

    Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at' => Carbon::parse('2026-04-02 11:00:00'),
    ]);

    $response = $this->getJson(route('admin.clinic-guide.get', ['date' => '2026-04-01']));

    $response->assertOk();
    $response->assertJsonPath('count', 1);
});

test('clinic guide get excludes orders without first_examination_at', function () {
    $salesLead = SalesLead::factory()->create();

    Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'sales_lead_id'        => $salesLead->id,
        'first_examination_at' => null,
    ]);

    $response = $this->getJson(route('admin.clinic-guide.get', ['date' => now()->format('Y-m-d')]));

    $response->assertOk();
    $response->assertJsonPath('count', 0);
});

test('clinic guide get response contains expected fields', function () {
    $person = Person::factory()->create([
        'name' => 'Jan Testpatiënt',
    ]);

    $salesLead = SalesLead::factory()->create();
    $salesLead->attachPersons([$person->id]);

    $product = Product::factory()->create(['name' => 'MRI Scan']);

    $targetDate = '2026-05-10';
    $order = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at' => Carbon::parse($targetDate)->setHour(9)->setMinute(15),
    ]);

    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'quantity'   => 1,
    ]);

    $response = $this->getJson(route('admin.clinic-guide.get', ['date' => $targetDate]));

    $response->assertOk();
    $response->assertJsonPath('count', 1);

    $orderData = $response->json('orders.0');

    // Order fields
    expect($orderData['order'])->toHaveKeys(['id', 'title', 'first_examination_at', 'time', 'total_price'])
    ->and($orderData['order']['time'])->toBe('09:15');

    // Sales lead fields
    expect($orderData['sales_lead'])->toHaveKeys(['id', 'name']);

    // Patient fields
    expect($orderData['patient'])->toHaveKeys(['id', 'name']);

    // Order items
    expect($orderData['order_items'])->toHaveCount(1)
    ->and($orderData['order_items'][0]['product_name'])->toBe('MRI Scan');

    // Sales lead URL
    expect($orderData['sales_lead_url'])->toContain('sales-leads/view');
});

test('clinic guide get orders are sorted by time ascending', function () {
    $salesLead = SalesLead::factory()->create();
    $targetDate = '2026-06-01';

    $orderLate = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at' => Carbon::parse($targetDate)->setHour(16)->setMinute(0),
    ]);

    $orderEarly = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at' => Carbon::parse($targetDate)->setHour(8)->setMinute(0),
    ]);

    $orderMid = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at' => Carbon::parse($targetDate)->setHour(12)->setMinute(0),
    ]);

    $response = $this->getJson(route('admin.clinic-guide.get', ['date' => $targetDate]));

    $response->assertOk();
    $orderIds = collect($response->json('orders'))->pluck('order.id')->all();

    expect($orderIds)->toBe([$orderEarly->id, $orderMid->id, $orderLate->id]);
});

test('clinic guide index page loads successfully', function () {
    $response = $this->get(route('admin.clinic-guide.index'));

    $response->assertOk();
    $response->assertViewIs('adminc::clinic_guide.index');
});

test('clinic guide get defaults to today when no date parameter', function () {
    $salesLead = SalesLead::factory()->create();

    Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at' => now()->setHour(10)->setMinute(0)->setSecond(0),
    ]);

    $response = $this->getJson(route('admin.clinic-guide.get'));

    $response->assertOk();
    $response->assertJsonPath('date', now()->format('Y-m-d'));
    $response->assertJsonPath('count', 1);
});
