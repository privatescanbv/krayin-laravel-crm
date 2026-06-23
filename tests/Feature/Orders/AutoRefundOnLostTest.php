<?php

use App\Enums\OrderItemStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\PipelineStage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Services\OrderRefundService;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'user');
});

// ── Helper ──────────────────────────────────────────────────────────────────

function makeOrderWithPayment(float $itemPrice, float $paidAmount, PaymentMethod $method = PaymentMethod::BANK): array
{
    $order = Order::factory()->create(['total_price' => $itemPrice]);
    $item = OrderItem::factory()->create([
        'order_id'    => $order->id,
        'total_price' => $itemPrice,
        'status'      => OrderItemStatus::PLANNED,
    ]);

    if ($paidAmount > 0) {
        OrderPayment::create([
            'order_id'   => $order->id,
            'amount'     => $paidAmount,
            'type'       => PaymentType::ADVANCE,
            'method'     => $method,
            'paid_at'    => now(),
            'currency'   => 'EUR',
            'created_by' => null,
        ]);
    }

    return [$order->fresh(), $item];
}

// ── 1: Surplus aanwezig → REFUND aangemaakt via observer ────────────────────

test('item LOST met surplus créeert automatisch een REFUND betaling en taakactiviteit', function () {
    [$order, $item] = makeOrderWithPayment(itemPrice: 200.00, paidAmount: 200.00);

    $item->update(['status' => OrderItemStatus::LOST]);

    $order->refresh()->load('payments');

    $refunds = $order->payments->where('type', PaymentType::REFUND);
    expect($refunds)->toHaveCount(1);

    $refund = $refunds->first();
    expect((float) $refund->amount)->toBe(200.00)
        ->and($refund->paid_at)->toBeNull()
        ->and($refund->method)->toBe(PaymentMethod::BANK);

    $this->assertDatabaseHas('activities', [
        'order_id' => $order->id,
        'type'     => 'task',
        'title'    => 'Klant terugbetalen',
        'is_done'  => false,
    ]);
});

// ── 2: Geen surplus → geen REFUND ───────────────────────────────────────────

test('item LOST zonder surplus créeert geen REFUND', function () {
    [$order, $item] = makeOrderWithPayment(itemPrice: 200.00, paidAmount: 100.00);

    // Voeg tweede item toe zodat totaal na LOST nog 100 is.
    OrderItem::factory()->create([
        'order_id'    => $order->id,
        'total_price' => 100.00,
        'status'      => OrderItemStatus::PLANNED,
    ]);
    $order->recalculateTotalPrice();

    $item->update(['status' => OrderItemStatus::LOST]);

    $order->refresh()->load('payments');
    expect($order->payments->where('type', PaymentType::REFUND))->toHaveCount(0);
});

// ── 3: Hele order naar LOST stage (OrderObserver bulk-pad) ──────────────────

test('order naar LOST pipeline stage créeert REFUND voor het totale surplus', function () {
    $lostStageId = PipelineStage::ORDER_VERLOREN->id();

    $order = Order::factory()->create(['total_price' => 0]); // wordt herberekend
    OrderItem::factory()->create([
        'order_id'    => $order->id,
        'total_price' => 300.00,
        'status'      => OrderItemStatus::PLANNED,
    ]);
    $order->recalculateTotalPrice();

    OrderPayment::create([
        'order_id'   => $order->id,
        'amount'     => 300.00,
        'type'       => PaymentType::ADVANCE,
        'method'     => PaymentMethod::PIN,
        'paid_at'    => now(),
        'currency'   => 'EUR',
        'created_by' => null,
    ]);

    // Verplaats naar LOST stage → OrderObserver bulk-markeert items en maakt refund
    $order->update(['pipeline_stage_id' => $lostStageId]);

    $order->refresh()->load('payments');

    $refunds = $order->payments->where('type', PaymentType::REFUND);
    expect($refunds)->toHaveCount(1);
    expect((float) $refunds->first()->amount)->toBe(300.00);
});

// ── 4: Twee items achtereenvolgens LOST → twee REFUNDs, saldo nul ───────────

test('twee items achtereenvolgens op LOST: saldo is nul na beide REFUNDs', function () {
    $order = Order::factory()->create(['total_price' => 0]);
    $itemA = OrderItem::factory()->create([
        'order_id'    => $order->id,
        'total_price' => 150.00,
        'status'      => OrderItemStatus::PLANNED,
    ]);
    $itemB = OrderItem::factory()->create([
        'order_id'    => $order->id,
        'total_price' => 150.00,
        'status'      => OrderItemStatus::PLANNED,
    ]);
    $order->recalculateTotalPrice();

    OrderPayment::create([
        'order_id'   => $order->id,
        'amount'     => 300.00,
        'type'       => PaymentType::ADVANCE,
        'method'     => PaymentMethod::BANK,
        'paid_at'    => now(),
        'currency'   => 'EUR',
        'created_by' => null,
    ]);

    $itemA->update(['status' => OrderItemStatus::LOST]);
    $itemB->update(['status' => OrderItemStatus::LOST]);

    $order->refresh()->load('payments');

    $totalRefunded = $order->payments
        ->where('type', PaymentType::REFUND)
        ->sum(fn ($p) => (float) $p->amount);

    expect($totalRefunded)->toBe(300.00)
        ->and($order->netPaidAmount())->toBe(0.0);
});

// ── 5: Idempotentie: service twee keer aanroepen maakt geen dubbele REFUND ──

test('createRefundIfSurplus is idempotent', function () {
    [$order, $item] = makeOrderWithPayment(itemPrice: 100.00, paidAmount: 100.00);

    $item->update(['status' => OrderItemStatus::LOST]);

    $service = app(OrderRefundService::class);

    // Tweede aanroep: surplus is 0 door de eerste REFUND → geen extra
    $second = $service->createRefundIfSurplus($order->fresh());
    expect($second)->toBeNull();

    $order->refresh()->load('payments');
    expect($order->payments->where('type', PaymentType::REFUND))->toHaveCount(1);
});

// ── 6: Betaalmethode overgenomen van laatste niet-refund betaling ────────────

test('REFUND overneemt betaalmethode van de laatste betaling', function () {
    [$order, $item] = makeOrderWithPayment(itemPrice: 200.00, paidAmount: 100.00, method: PaymentMethod::BANK);

    // Tweede betaling met PIN (de meest recente)
    OrderPayment::create([
        'order_id'   => $order->id,
        'amount'     => 100.00,
        'type'       => PaymentType::ADVANCE,
        'method'     => PaymentMethod::PIN,
        'paid_at'    => now(),
        'currency'   => 'EUR',
        'created_by' => null,
    ]);

    $item->update(['status' => OrderItemStatus::LOST]);

    $order->refresh()->load('payments');
    $refund = $order->payments->where('type', PaymentType::REFUND)->first();

    expect($refund)->not->toBeNull()
        ->and($refund->method)->toEqual(PaymentMethod::PIN);
});

// ── 7: Controller HTTP-pad geeft refund_created in JSON-respons ─────────────

test('PUT order via controller geeft refund_created mee in respons', function () {
    $order = Order::factory()->create(['total_price' => 0]);
    $item = OrderItem::factory()->create([
        'order_id'    => $order->id,
        'total_price' => 250.00,
        'status'      => OrderItemStatus::PLANNED,
    ]);
    $order->recalculateTotalPrice();

    OrderPayment::create([
        'order_id'   => $order->id,
        'amount'     => 250.00,
        'type'       => PaymentType::ADVANCE,
        'method'     => PaymentMethod::BANK,
        'paid_at'    => now(),
        'currency'   => 'EUR',
        'created_by' => null,
    ]);

    $this->putJson(route('admin.orders.update', $order->id), [
        'title'                  => $order->title,
        'sales_lead_id'          => $order->sales_lead_id,
        'pipeline_stage_id'      => $order->pipeline_stage_id,
        'removed_order_item_ids' => [$item->id],
    ])->assertOk()
        ->assertJsonPath('refund_created', true)
        ->assertJson(fn ($json) => $json->where('refund_amount', 250)->etc());

    $this->assertDatabaseHas('order_payments', [
        'order_id' => $order->id,
        'type'     => PaymentType::REFUND->value,
        'amount'   => '250.00',
        'paid_at'  => null,
    ]);
});
