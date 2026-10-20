<?php

use App\Enums\OrderItemStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\PipelineStage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->actingAs(User::factory()->create(), 'user');

    $this->order = Order::factory()->create(['pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id()]);
    $this->active = OrderItem::factory()->create(['order_id' => $this->order->id, 'total_price' => 750, 'status' => OrderItemStatus::NEW->value]);
    $this->other = OrderItem::factory()->create(['order_id' => $this->order->id, 'total_price' => 59, 'status' => OrderItemStatus::NEW->value]);
    // Removed from the order on its own before the order was lost.
    $this->removed = OrderItem::factory()->create(['order_id' => $this->order->id, 'total_price' => 450, 'status' => OrderItemStatus::NEW->value]);
    $this->removed->update(['status' => OrderItemStatus::LOST->value]);
});

test('setting an order to verloren flags only the lines that were still active', function () {
    $this->order->fresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_VERLOREN->id()]);

    expect($this->active->fresh())->status->toBe(OrderItemStatus::LOST)->lost_with_order->toBeTrue()
        ->and($this->other->fresh())->status->toBe(OrderItemStatus::LOST)->lost_with_order->toBeTrue()
        ->and($this->removed->fresh())->status->toBe(OrderItemStatus::LOST)->lost_with_order->toBeFalse()
        // Lost order: total_price is the sum of all lines (lost revenue).
        ->and((float) $this->order->fresh()->total_price)->toBe(1259.0);
});

test('reopening a lost order restores the lines lost with the order, not the ones removed before', function () {
    $this->order->fresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_VERLOREN->id()]);
    $this->order->fresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id()]);

    expect($this->active->fresh())->status->toBe(OrderItemStatus::NEW)->lost_with_order->toBeFalse()
        ->and($this->other->fresh())->status->toBe(OrderItemStatus::NEW)->lost_with_order->toBeFalse()
        ->and($this->removed->fresh()->status)->toBe(OrderItemStatus::LOST)
        ->and((float) $this->order->fresh()->total_price)->toBe(809.0)
        ->and($this->order->fresh()->payableTotal())->toBe(809.0);
});

test('reopening a lost order straight to gewonnen sets the restored lines to won', function () {
    // Order 4228 scenario: Order akkoord → Verloren → Gewonnen, already paid.
    OrderPayment::create([
        'order_id' => $this->order->id,
        'amount'   => 809,
        'type'     => PaymentType::ADVANCE,
        'method'   => PaymentMethod::BANK,
        'paid_at'  => now(),
        'currency' => 'EUR',
    ]);

    $this->order->fresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_VERLOREN->id()]);
    $this->order->fresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id()]);

    $order = $this->order->fresh();

    expect($this->active->fresh()->status)->toBe(OrderItemStatus::WON)
        ->and($this->other->fresh()->status)->toBe(OrderItemStatus::WON)
        ->and($this->removed->fresh()->status)->toBe(OrderItemStatus::LOST)
        ->and((float) $order->total_price)->toBe(809.0)
        ->and($order->paymentStatus())->toBe(OrderPaymentStatus::FULLY_PAID);
});
