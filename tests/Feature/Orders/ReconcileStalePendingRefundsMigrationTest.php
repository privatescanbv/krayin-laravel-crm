<?php

use App\Enums\OrderItemStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderRefundService;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

/**
 * Order zoals 11395: regels 709 actief + 550 verloren + 550 annuleringskosten, 1259 betaald,
 * plus een openstaande refund met open taak (aangemaakt vóór de annuleringskosten).
 *
 * @param  list<array{0: OrderItemStatus, 1: float}>  $lines
 */
function orderWithPendingRefund(array $lines, float $paid, float $refund, bool $autoCreated = true): Order
{
    $order = Order::factory()->create();

    foreach ($lines as [$status, $price]) {
        OrderItem::factory()->create(['order_id' => $order->id, 'total_price' => $price, 'status' => $status]);
    }

    $payment = ['order_id' => $order->id, 'method' => PaymentMethod::BANK->value, 'currency' => 'EUR', 'created_at' => now(), 'updated_at' => now()];
    DB::table('order_payments')->insert([
        $payment + ['amount' => $paid, 'type' => PaymentType::ADVANCE->value, 'paid_at' => now(), 'auto_created' => false],
        $payment + ['amount' => $refund, 'type' => PaymentType::REFUND->value, 'paid_at' => null, 'auto_created' => $autoCreated],
    ]);

    DB::table('activities')->insert([
        'type'       => 'task',
        'title'      => OrderRefundService::TASK_TITLE,
        'order_id'   => $order->id,
        'is_done'    => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $order;
}

function runReconcileMigration(): void
{
    (require database_path('migrations/2026_10_20_180000_reconcile_stale_pending_refunds.php'))->up();
}

function pendingRefunds(Order $order): array
{
    return DB::table('order_payments')->where('order_id', $order->id)->where('type', PaymentType::REFUND->value)
        ->whereNull('paid_at')->pluck('amount')->map(fn ($a) => (float) $a)->all();
}

function openRefundTasks(Order $order): int
{
    return DB::table('activities')->where('order_id', $order->id)->where('title', OrderRefundService::TASK_TITLE)->where('is_done', false)->count();
}

test('migration removes an automatic refund that is no longer owed after a line was added', function () {
    $order = orderWithPendingRefund([
        [OrderItemStatus::PLANNED, 650], [OrderItemStatus::NEW, 59], [OrderItemStatus::LOST, 550], [OrderItemStatus::NEW, 550],
    ], paid: 1259, refund: 550);

    runReconcileMigration();

    expect(pendingRefunds($order))->toBe([])
        ->and(openRefundTasks($order))->toBe(0)
        ->and($order->fresh()->paymentStatus())->toBe(OrderPaymentStatus::FULLY_PAID);
    $this->assertDatabaseHas('activities', ['order_id' => $order->id, 'type' => 'system', 'title' => 'Automatische terugbetaling van € 550,00 vervallen']);
    $this->assertDatabaseHas('activities', ['order_id' => $order->id, 'type' => 'system', 'title' => "Taak 'Klant terugbetalen' automatisch afgerond"]);
});

test('migration lowers an automatic refund that is only partly owed', function () {
    $order = orderWithPendingRefund([[OrderItemStatus::PLANNED, 900]], paid: 1259, refund: 550);

    runReconcileMigration();

    expect(pendingRefunds($order))->toBe([359.0])
        ->and(openRefundTasks($order))->toBe(1);
});

test('migration leaves a refund that is still owed untouched', function () {
    $order = orderWithPendingRefund([[OrderItemStatus::PLANNED, 709], [OrderItemStatus::LOST, 550]], paid: 1259, refund: 550);

    runReconcileMigration();

    expect(pendingRefunds($order))->toBe([550.0])
        ->and(openRefundTasks($order))->toBe(1);
});

test('migration never touches a manually entered refund', function () {
    $order = orderWithPendingRefund([[OrderItemStatus::PLANNED, 1259]], paid: 1259, refund: 550, autoCreated: false);

    runReconcileMigration();

    expect(pendingRefunds($order))->toBe([550.0]);
});
