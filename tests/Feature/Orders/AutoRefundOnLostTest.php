<?php

use App\Enums\OrderItemStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\PipelineStage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Services\OrderRefundService;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
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

// ── Verloren order volledig betaald → credit + volledige REFUND ─────────────

test('verloren order van 809 die volledig betaald is krijgt status credit en een REFUND van 809', function () {
    [$order] = makeOrderWithPayment(itemPrice: 809.00, paidAmount: 809.00);

    $order->update(['pipeline_stage_id' => PipelineStage::ORDER_VERLOREN->id()]);

    $order = $order->fresh();
    $refund = $order->payments()->where('type', PaymentType::REFUND->value)->sole();

    expect((float) $order->total_price)->toBe(809.0)
        ->and($order->paymentStatus())->toBe(OrderPaymentStatus::CREDIT)
        ->and((float) $refund->amount)->toBe(809.0)
        ->and($refund->paid_at)->toBeNull();

    $this->assertDatabaseHas('activities', [
        'order_id' => $order->id,
        'type'     => 'task',
        'title'    => 'Klant terugbetalen',
    ]);
});

test('gewonnen order met alleen verloren regels heeft totaal 0 en is credit bij betaling', function () {
    [$order, $item] = makeOrderWithPayment(itemPrice: 750.00, paidAmount: 809.00);
    OrderItem::factory()->create(['order_id' => $order->id, 'total_price' => 59.00, 'status' => OrderItemStatus::LOST]);
    $item->update(['status' => OrderItemStatus::LOST]);

    $order = $order->fresh();

    expect((float) $order->total_price)->toBe(0.0)
        ->and($order->lostItemsTotal())->toBe(809.0)
        ->and($order->paymentStatus())->toBe(OrderPaymentStatus::CREDIT);
});

test('openstaande refund vervalt als er na het verliezen een regel met hetzelfde bedrag bijkomt', function () {
    [$order, $item] = makeOrderWithPayment(itemPrice: 550.00, paidAmount: 1259.00);
    OrderItem::factory()->create(['order_id' => $order->id, 'total_price' => 709.00, 'status' => OrderItemStatus::PLANNED]);

    // Knie verloren → automatische refund van 550, daarna annuleringskosten van 550 toegevoegd.
    $item->update(['status' => OrderItemStatus::LOST]);
    expect($order->fresh()->payments->where('type', PaymentType::REFUND))->toHaveCount(1);

    OrderItem::factory()->create(['order_id' => $order->id, 'total_price' => 550.00, 'status' => OrderItemStatus::NEW]);
    app(OrderRefundService::class)->createRefundIfSurplus($order, $this->user->id);

    $order = $order->fresh();
    expect($order->payments->where('type', PaymentType::REFUND))->toHaveCount(0)
        ->and($order->netPaidAmount())->toBe(1259.0)
        ->and($order->paymentStatus())->toBe(OrderPaymentStatus::FULLY_PAID);
    $this->assertDatabaseMissing('activities', ['order_id' => $order->id, 'title' => 'Klant terugbetalen', 'is_done' => false]);
    $this->assertDatabaseHas('activities', [
        'order_id' => $order->id,
        'type'     => 'system',
        'title'    => 'Automatische terugbetaling van € 550,00 vervallen',
    ]);
});

test('openstaande refund wordt verlaagd als een deel van het surplus vervalt', function () {
    [$order, $item] = makeOrderWithPayment(itemPrice: 550.00, paidAmount: 1259.00);
    OrderItem::factory()->create(['order_id' => $order->id, 'total_price' => 709.00, 'status' => OrderItemStatus::PLANNED]);

    $item->update(['status' => OrderItemStatus::LOST]);
    OrderItem::factory()->create(['order_id' => $order->id, 'total_price' => 100.00, 'status' => OrderItemStatus::NEW]);
    app(OrderRefundService::class)->createRefundIfSurplus($order, $this->user->id);

    $refunds = $order->fresh()->payments->where('type', PaymentType::REFUND);
    expect($refunds)->toHaveCount(1)
        ->and((float) $refunds->first()->amount)->toBe(450.0);
    $this->assertDatabaseHas('activities', [
        'order_id' => $order->id,
        'type'     => 'system',
        'title'    => 'Automatische terugbetaling verlaagd: € 550,00 → € 450,00',
    ]);
});

test('uitbetaalde refund wordt nooit aangepast', function () {
    [$order, $item] = makeOrderWithPayment(itemPrice: 550.00, paidAmount: 1259.00);
    OrderItem::factory()->create(['order_id' => $order->id, 'total_price' => 709.00, 'status' => OrderItemStatus::PLANNED]);

    $item->update(['status' => OrderItemStatus::LOST]);
    OrderPayment::where('order_id', $order->id)->where('type', PaymentType::REFUND->value)->update(['paid_at' => now()]);
    OrderItem::factory()->create(['order_id' => $order->id, 'total_price' => 550.00, 'status' => OrderItemStatus::NEW]);
    app(OrderRefundService::class)->createRefundIfSurplus($order, $this->user->id);

    expect((float) $order->fresh()->payments->where('type', PaymentType::REFUND)->first()->amount)->toBe(550.0);
});

test('handmatig ingevoerde openstaande refund wordt nooit aangepast', function () {
    [$order] = makeOrderWithPayment(itemPrice: 1259.00, paidAmount: 1259.00);
    OrderPayment::create([
        'order_id' => $order->id,
        'amount'   => 100.00,
        'type'     => PaymentType::REFUND,
        'method'   => PaymentMethod::BANK,
        'paid_at'  => null,
        'currency' => 'EUR',
    ]);

    app(OrderRefundService::class)->createRefundIfSurplus($order, $this->user->id);

    $refunds = $order->fresh()->payments->where('type', PaymentType::REFUND);
    expect($refunds)->toHaveCount(1)
        ->and((float) $refunds->first()->amount)->toBe(100.0)
        ->and($refunds->first()->auto_created)->toBeFalse();
    $this->assertDatabaseMissing('activities', ['order_id' => $order->id, 'type' => 'system']);
});

test('automatisch aangemaakte refund krijgt de auto_created vlag', function () {
    [$order, $item] = makeOrderWithPayment(itemPrice: 200.00, paidAmount: 200.00);

    $item->update(['status' => OrderItemStatus::LOST]);

    expect($order->fresh()->payments->where('type', PaymentType::REFUND)->first()->auto_created)->toBeTrue();
});

// ── Auto-taak "Klant terugbetalen" sluit vanzelf ────────────────────────────

function openRefundTaskCount(Order $order): int
{
    return $order->activities()->where('title', OrderRefundService::TASK_TITLE)->where('is_done', false)->count();
}

test('taak klant terugbetalen sluit en wordt gelogd zodra de refund een datum krijgt', function () {
    $this->withoutMiddleware(Authenticate::class);
    [$order, $item] = makeOrderWithPayment(itemPrice: 200.00, paidAmount: 200.00);
    $item->update(['status' => OrderItemStatus::LOST]);
    $refund = $order->payments()->where('type', PaymentType::REFUND->value)->first();
    expect(openRefundTaskCount($order))->toBe(1);

    $this->putJson(route('admin.orders.payments.update', ['orderId' => $order->id, 'paymentId' => $refund->id]), [
        'amount'   => 200,
        'type'     => PaymentType::REFUND->value,
        'method'   => PaymentMethod::BANK->value,
        'paid_at'  => now()->toDateString(),
        'currency' => 'EUR',
    ])->assertOk();

    expect(openRefundTaskCount($order))->toBe(0);
    $this->assertDatabaseHas('activities', [
        'order_id' => $order->id,
        'type'     => 'system',
        'title'    => "Taak 'Klant terugbetalen' automatisch afgerond",
    ]);
});

test('taak klant terugbetalen sluit als de refund verwijderd wordt', function () {
    $this->withoutMiddleware(Authenticate::class);
    [$order, $item] = makeOrderWithPayment(itemPrice: 200.00, paidAmount: 200.00);
    $item->update(['status' => OrderItemStatus::LOST]);
    $refund = $order->payments()->where('type', PaymentType::REFUND->value)->first();

    $this->deleteJson(route('admin.orders.payments.destroy', ['orderId' => $order->id, 'paymentId' => $refund->id]))
        ->assertOk();

    expect(openRefundTaskCount($order))->toBe(0);
});

test('taak klant terugbetalen sluit als het totaal weer betaald is, ook met een handmatige openstaande refund', function () {
    [$order, $item] = makeOrderWithPayment(itemPrice: 550.00, paidAmount: 1259.00);
    OrderItem::factory()->create(['order_id' => $order->id, 'total_price' => 709.00, 'status' => OrderItemStatus::PLANNED]);
    $item->update(['status' => OrderItemStatus::LOST]);

    // Refund als handmatig behandelen: wordt niet automatisch verlaagd, maar de taak mag wel dicht.
    OrderPayment::where('order_id', $order->id)->where('type', PaymentType::REFUND->value)->update(['auto_created' => false]);
    expect(openRefundTaskCount($order))->toBe(1);

    OrderItem::factory()->create(['order_id' => $order->id, 'total_price' => 550.00, 'status' => OrderItemStatus::NEW]);
    app(OrderRefundService::class)->createRefundIfSurplus($order, $this->user->id);

    expect(openRefundTaskCount($order))->toBe(0)
        ->and($order->fresh()->payments->where('type', PaymentType::REFUND))->toHaveCount(1);
});

test('taak klant terugbetalen blijft open zolang er nog een refund openstaat', function () {
    [$order, $item] = makeOrderWithPayment(itemPrice: 200.00, paidAmount: 300.00);
    $second = OrderItem::factory()->create(['order_id' => $order->id, 'total_price' => 100.00, 'status' => OrderItemStatus::PLANNED]);
    $item->update(['status' => OrderItemStatus::LOST]);
    $second->update(['status' => OrderItemStatus::LOST]);

    $refunds = $order->payments()->where('type', PaymentType::REFUND->value)->orderBy('id')->get();
    expect($refunds)->toHaveCount(2)
        ->and(openRefundTaskCount($order))->toBe(2);

    $refunds->first()->update(['paid_at' => now()]);
    expect(openRefundTaskCount($order))->toBe(2);

    $refunds->last()->update(['paid_at' => now()]);
    expect(openRefundTaskCount($order))->toBe(0);
});
