<?php

use App\Enums\OrderItemStatus;
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

function orderWithOpenRefundTask(float $itemPrice, float $paid, ?string $refundPaidAt): Order
{
    $order = Order::factory()->create(['total_price' => $itemPrice]);
    OrderItem::factory()->create(['order_id' => $order->id, 'total_price' => $itemPrice, 'status' => OrderItemStatus::WON]);

    DB::table('order_payments')->insert([
        ['order_id' => $order->id, 'amount' => $paid, 'type' => PaymentType::ADVANCE->value, 'method' => PaymentMethod::BANK->value, 'paid_at' => now(), 'currency' => 'EUR', 'created_at' => now(), 'updated_at' => now()],
        ['order_id' => $order->id, 'amount' => $paid - $itemPrice, 'type' => PaymentType::REFUND->value, 'method' => PaymentMethod::BANK->value, 'paid_at' => $refundPaidAt, 'currency' => 'EUR', 'created_at' => now(), 'updated_at' => now()],
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

test('migration closes refund tasks of settled orders and keeps those still to refund open', function () {
    $refundPaid = orderWithOpenRefundTask(itemPrice: 200, paid: 300, refundPaidAt: now()->toDateString());
    $stillToRefund = orderWithOpenRefundTask(itemPrice: 200, paid: 300, refundPaidAt: null);

    (require database_path('migrations/2026_10_20_170000_close_settled_refund_tasks.php'))->up();

    $open = fn (Order $order) => DB::table('activities')
        ->where('order_id', $order->id)->where('title', OrderRefundService::TASK_TITLE)->where('is_done', false)->count();

    expect($open($refundPaid))->toBe(0)
        ->and($open($stillToRefund))->toBe(1);
    $this->assertDatabaseHas('activities', ['order_id' => $refundPaid->id, 'type' => 'system', 'title' => "Taak 'Klant terugbetalen' automatisch afgerond"]);
});
