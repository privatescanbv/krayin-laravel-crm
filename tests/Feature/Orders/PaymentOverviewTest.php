<?php

use App\Enums\Currency;
use App\Enums\OrderItemStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStage;
use App\Enums\PipelineType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    $this->user = User::factory()->create(['name' => 'Admin Payment Overview']);
    $this->actingAs($this->user, 'user');
    $this->withoutMiddleware(Authenticate::class);

    $this->pipeline = Pipeline::factory()->create([
        'name'        => 'Order Pipeline Payment Overview',
        'type'        => PipelineType::ORDER,
        'is_default'  => 0,
        'rotten_days' => 0,
    ]);

    $this->stageOpen = Stage::factory()->create([
        'lead_pipeline_id' => $this->pipeline->id,
        'name'             => 'Open',
        'code'             => 'order_payment_open',
        'sort_order'       => 1,
        'is_won'           => false,
        'is_lost'          => false,
    ]);

    $this->stageLost = Stage::factory()->create([
        'lead_pipeline_id' => $this->pipeline->id,
        'name'             => 'Verloren',
        'code'             => 'order_payment_lost',
        'sort_order'       => 2,
        'is_won'           => false,
        'is_lost'          => true,
    ]);
});

test('payment overview excludes orders in verloren pipeline stage', function () {
    $suffix = uniqid('', true);

    $orderOpen = Order::factory()->create([
        'pipeline_stage_id'   => $this->stageOpen->id,
        'title'               => 'Betaaloverzicht open order '.$suffix,
        'total_price'         => 100.00,
        'first_examination_at'=> now(),
    ]);

    $orderLost = Order::factory()->create([
        'pipeline_stage_id'   => $this->stageLost->id,
        'title'               => 'Betaaloverzicht verloren order '.$suffix,
        'total_price'         => 100.00,
        'first_examination_at'=> now(),
    ]);

    $response = $this->get(route('admin.orders.payment-overview', [
        'pipeline_id' => $this->pipeline->id,
    ]));

    $response->assertOk();
    $response->assertSee($orderOpen->title, false);
    $response->assertDontSee($orderLost->title, false);
});

function paymentOverviewPayment(Order $order, float $amount, PaymentType $type, ?string $paidAt): OrderPayment
{
    return OrderPayment::create([
        'order_id' => $order->id,
        'amount'   => $amount,
        'type'     => $type,
        'method'   => PaymentMethod::BANK,
        'paid_at'  => $paidAt,
        'currency' => 'EUR',
    ]);
}

test('payment overview shows a lost order with a credit to refund', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id'    => $this->stageLost->id,
        'title'                => 'Verloren order met credit '.uniqid(),
        'total_price'          => 0.00,
        'first_examination_at' => null,
    ]);
    paymentOverviewPayment($order, 2500, PaymentType::ADVANCE, now()->toDateString());

    $this->get(route('admin.orders.payment-overview', ['pipeline_id' => $this->pipeline->id]))
        ->assertOk()
        ->assertSee($order->title, false);
});

test('payment overview hides a lost order once the refund is paid out', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => $this->stageLost->id,
        'title'             => 'Verloren order terugbetaald '.uniqid(),
        'total_price'       => 0.00,
    ]);
    paymentOverviewPayment($order, 2500, PaymentType::ADVANCE, now()->toDateString());
    paymentOverviewPayment($order, 2500, PaymentType::REFUND, now()->toDateString());

    $this->get(route('admin.orders.payment-overview', ['pipeline_id' => $this->pipeline->id]))
        ->assertOk()
        ->assertDontSee($order->title, false);
});

test('payment overview shows an open order with a credit even without examination date', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id'    => $this->stageOpen->id,
        'title'                => 'Open order met credit zonder datum '.uniqid(),
        'total_price'          => 100.00,
        'first_examination_at' => null,
    ]);
    paymentOverviewPayment($order, 150, PaymentType::ADVANCE, now()->toDateString());

    $this->get(route('admin.orders.payment-overview', ['pipeline_id' => $this->pipeline->id]))
        ->assertOk()
        ->assertSee($order->title, false);
});

test('payment overview still hides an open order without examination date that is not paid', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id'    => $this->stageOpen->id,
        'title'                => 'Open order zonder datum '.uniqid(),
        'total_price'          => 100.00,
        'first_examination_at' => null,
    ]);

    $this->get(route('admin.orders.payment-overview', ['pipeline_id' => $this->pipeline->id]))
        ->assertOk()
        ->assertDontSee($order->title, false);
});

test('order set to lost after a deposit keeps its total, gets a full refund and stays in the overview until it is paid out', function () {
    // Scenario from acceptance: order of 5000, 2500 paid, order set to Verloren.
    $order = Order::factory()->create([
        'pipeline_stage_id'    => PipelineStage::ORDER_CONFIRM->id(),
        'title'                => 'Order 5000 verloren na aanbetaling '.uniqid(),
        'first_examination_at' => now()->addWeek(),
    ]);
    OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 1, 'total_price' => 5000]);
    paymentOverviewPayment($order, 2500, PaymentType::ADVANCE, now()->toDateString());

    $overviewUrl = route('admin.orders.payment-overview', ['pipeline_id' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ORDERS_ID->value]);
    $this->get($overviewUrl)->assertOk()->assertSee($order->title, false);

    $order->refresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_VERLOREN->id()]);

    $openRefund = OrderPayment::where('order_id', $order->id)->where('type', PaymentType::REFUND->value)->sole();
    expect((float) $order->fresh()->total_price)->toBe(5000.0)
        ->and($order->fresh()->paymentStatus())->toBe(OrderPaymentStatus::CREDIT)
        ->and((float) $openRefund->amount)->toBe(2500.0)
        ->and($openRefund->paid_at)->toBeNull();

    $this->get($overviewUrl)->assertOk()->assertSee($order->title, false);

    $openRefund->update(['paid_at' => now()->toDateString()]);

    $this->get($overviewUrl)
        ->assertOk()
        ->assertDontSee($order->title, false);
});

test('payment overview shows the full payment as credit for an order whose lines are all lost', function () {
    // Order 1983 scenario: not a lost order, but both lines lost while € 809 was paid.
    $order = Order::factory()->create([
        'pipeline_stage_id'    => $this->stageOpen->id,
        'title'                => 'Alle regels verloren '.uniqid(),
        'first_examination_at' => null,
    ]);
    OrderItem::factory()->create(['order_id' => $order->id, 'total_price' => 750, 'status' => OrderItemStatus::LOST->value]);
    OrderItem::factory()->create(['order_id' => $order->id, 'total_price' => 59, 'status' => OrderItemStatus::LOST->value]);
    paymentOverviewPayment($order, 809, PaymentType::ADVANCE, now()->toDateString());

    expect($order->fresh()->payableTotal())->toBe(0.0);

    $this->get(route('admin.orders.payment-overview', ['pipeline_id' => $this->pipeline->id]))
        ->assertOk()
        ->assertViewHas('orders', fn ($orders) => $orders->contains('id', $order->id))
        ->assertSee($order->title, false)
        ->assertSeeText('Credit '.Currency::formatMoney('EUR', 809), false)
        ->assertSee('placeholder="809.00"', false);
});

test('payment overview leaves fully paid orders out of the query and keeps partially paid ones', function () {
    $paid = Order::factory()->create([
        'pipeline_stage_id'    => $this->stageOpen->id,
        'first_examination_at' => now()->addWeek(),
    ]);
    OrderItem::factory()->create(['order_id' => $paid->id, 'total_price' => 500, 'status' => OrderItemStatus::NEW->value]);
    OrderItem::factory()->create(['order_id' => $paid->id, 'total_price' => 200, 'status' => OrderItemStatus::LOST->value]);
    paymentOverviewPayment($paid, 500, PaymentType::ADVANCE, now()->toDateString());

    $partial = Order::factory()->create([
        'pipeline_stage_id'    => $this->stageOpen->id,
        'first_examination_at' => now()->addWeek(),
    ]);
    OrderItem::factory()->create(['order_id' => $partial->id, 'total_price' => 500, 'status' => OrderItemStatus::NEW->value]);
    paymentOverviewPayment($partial, 600, PaymentType::ADVANCE, now()->toDateString());
    // An unpaid refund doesn't count as received back yet.
    paymentOverviewPayment($partial, 200, PaymentType::REFUND, null);
    paymentOverviewPayment($partial, 150, PaymentType::REFUND, now()->toDateString());

    $this->get(route('admin.orders.payment-overview', ['pipeline_id' => $this->pipeline->id]))
        ->assertOk()
        ->assertViewHas('orders', fn ($orders) => ! $orders->contains('id', $paid->id) && $orders->contains('id', $partial->id));
});

test('payment overview only counts the active lines of an order with a lost line as open', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id'    => $this->stageOpen->id,
        'title'                => 'Deels verloren '.uniqid(),
        'first_examination_at' => now()->addWeek(),
    ]);
    OrderItem::factory()->create(['order_id' => $order->id, 'total_price' => 850, 'status' => OrderItemStatus::NEW->value]);
    OrderItem::factory()->create(['order_id' => $order->id, 'total_price' => 450, 'status' => OrderItemStatus::LOST->value]);
    paymentOverviewPayment($order, 100, PaymentType::ADVANCE, now()->toDateString());

    $response = $this->get(route('admin.orders.payment-overview', ['pipeline_id' => $this->pipeline->id]))
        ->assertOk()
        ->assertSee($order->title, false);

    $html = $response->getContent();
    $start = strpos($html, $order->title);
    $row = substr($html, $start, strpos($html, '</tr>', $start) - $start);

    expect($row)->toContain(Currency::formatMoney('EUR', 850))
        ->and($row)->toContain(Currency::formatMoney('EUR', 750))
        ->and($row)->not->toContain(Currency::formatMoney('EUR', 1300))
        ->and($row)->not->toContain('Credit €');
});

test('order payments tab shows the amount to refund for a lost order with a credit', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => $this->stageLost->id,
        'total_price'       => 1949.00,
    ]);
    paymentOverviewPayment($order, 500, PaymentType::ADVANCE, now()->toDateString());

    $this->get(route('admin.orders.view', $order->id))
        ->assertOk()
        ->assertSeeInOrder(['Terug te betalen', '€ 500,00'], false);
});
