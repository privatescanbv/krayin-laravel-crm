<?php

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

test('payment overview shows a lost order that still has a credit to refund', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id'    => $this->stageLost->id,
        'title'                => 'Verloren order met credit '.uniqid(),
        'total_price'          => 0.00,
        'first_examination_at' => null,
    ]);
    paymentOverviewPayment($order, 2500, PaymentType::ADVANCE, now()->toDateString());

    $this->get(route('admin.orders.payment-overview', ['pipeline_id' => $this->pipeline->id]))
        ->assertOk()
        ->assertSee($order->title, false)
        ->assertSee('Credit', false);
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

test('order set to lost after a deposit appears as credit and the refund can be settled', function () {
    // Scenario from acceptance: order of 5000, 2500 paid, order set to Verloren.
    $order = Order::factory()->create([
        'pipeline_stage_id'    => PipelineStage::ORDER_CONFIRM->id(),
        'title'                => 'Order 5000 verloren na aanbetaling '.uniqid(),
        'first_examination_at' => now()->addWeek(),
    ]);
    OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 1, 'total_price' => 5000]);
    paymentOverviewPayment($order, 2500, PaymentType::ADVANCE, now()->toDateString());

    $order->refresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_VERLOREN->id()]);

    $openRefund = OrderPayment::where('order_id', $order->id)->where('type', PaymentType::REFUND->value)->sole();
    expect((float) $order->fresh()->total_price)->toBe(0.0)
        ->and((float) $openRefund->amount)->toBe(2500.0)
        ->and($openRefund->paid_at)->toBeNull();

    $overviewUrl = route('admin.orders.payment-overview', ['pipeline_id' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ORDERS_ID->value]);

    $this->get($overviewUrl)
        ->assertOk()
        ->assertSee($order->title, false)
        ->assertSee('Credit', false);

    // Paying out the refund from the overview settles the open refund instead of adding a second one.
    $this->postJson(route('admin.orders.payment-overview.save'), [
        'rows' => [[
            'order_id' => $order->id,
            'amount'   => 2500,
            'type'     => PaymentType::REFUND->value,
            'method'   => PaymentMethod::BANK->value,
            'paid_at'  => now()->toDateString(),
            'currency' => 'EUR',
        ]],
    ])->assertOk();

    $refunds = OrderPayment::where('order_id', $order->id)->where('type', PaymentType::REFUND->value)->get();
    expect($refunds)->toHaveCount(1)
        ->and($refunds->first()->id)->toBe($openRefund->id)
        ->and($refunds->first()->paid_at)->not->toBeNull();

    $this->get($overviewUrl)
        ->assertOk()
        ->assertDontSee($order->title, false);
});
