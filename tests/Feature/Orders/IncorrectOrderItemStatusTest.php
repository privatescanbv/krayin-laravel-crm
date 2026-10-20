<?php

use App\Enums\OrderItemStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\PipelineStage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\ResourceOrderItem;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Contact\Models\Person;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->actingAs(User::factory()->create(), 'user');

    $this->order = Order::factory()->create(['pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id()]);
    $this->kept = OrderItem::factory()->create(['order_id' => $this->order->id, 'total_price' => 750, 'status' => OrderItemStatus::NEW->value]);
    $this->wrong = OrderItem::factory()->create(['order_id' => $this->order->id, 'total_price' => 450, 'status' => OrderItemStatus::NEW->value]);
});

test('Foutief counts as lost with its own label', function () {
    expect(OrderItemStatus::INCORRECT->label())->toBe('Foutief')
        ->and(OrderItemStatus::INCORRECT->isLost())->toBeTrue()
        ->and(OrderItemStatus::LOST->isLost())->toBeTrue()
        ->and(OrderItemStatus::WON->isLost())->toBeFalse();
});

test('a line set to Foutief frees its planning and no longer counts for the order', function () {
    ResourceOrderItem::factory()->create(['orderitem_id' => $this->wrong->id]);

    $this->wrong->update(['status' => OrderItemStatus::INCORRECT->value]);

    $order = $this->order->fresh();

    expect(ResourceOrderItem::where('orderitem_id', $this->wrong->id)->exists())->toBeFalse()
        ->and((float) $order->total_price)->toBe(750.0)
        ->and($order->payableTotal())->toBe(750.0)
        ->and($order->lostItemsTotal())->toBe(0.0)
        ->and($order->revenueSplit(false))->toBe([750.0, 0.0]);
});

test('a line set to Foutief after payment creates a refund for the surplus', function () {
    OrderPayment::create([
        'order_id' => $this->order->id,
        'amount'   => 1200,
        'type'     => PaymentType::ADVANCE,
        'method'   => PaymentMethod::BANK,
        'paid_at'  => now(),
        'currency' => 'EUR',
    ]);

    $this->wrong->update(['status' => OrderItemStatus::INCORRECT->value]);

    $refund = OrderPayment::where('order_id', $this->order->id)->where('type', PaymentType::REFUND->value)->sole();

    expect((float) $refund->amount)->toBe(450.0)
        ->and($this->order->fresh()->paymentStatus())->toBe(OrderPaymentStatus::CREDIT);
});

test('setting the order to verloren keeps Foutief lines out of total_price and lost revenue', function () {
    $this->wrong->update(['status' => OrderItemStatus::INCORRECT->value]);

    $this->order->fresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_VERLOREN->id()]);

    $order = $this->order->fresh();

    expect($this->wrong->fresh())->status->toBe(OrderItemStatus::INCORRECT)->lost_with_order->toBeFalse()
        ->and($this->kept->fresh())->status->toBe(OrderItemStatus::LOST)->lost_with_order->toBeTrue()
        // Lost order: total_price = LOST lines only.
        ->and((float) $order->total_price)->toBe(750.0)
        ->and($order->revenueSplit(true))->toBe([0.0, 750.0]);
});

test('reopening a lost order leaves Foutief lines untouched', function () {
    $this->wrong->update(['status' => OrderItemStatus::INCORRECT->value]);
    $this->order->fresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_VERLOREN->id()]);

    $this->order->fresh()->update(['pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id()]);

    expect($this->wrong->fresh()->status)->toBe(OrderItemStatus::INCORRECT)
        ->and($this->kept->fresh()->status)->toBe(OrderItemStatus::NEW)
        ->and((float) $this->order->fresh()->total_price)->toBe(750.0);
});

test('the order item update endpoint accepts Foutief', function () {
    $this->withoutMiddleware(Authenticate::class);

    $this->put(route('admin.order_items.update', $this->wrong->id), [
        'order_id'    => $this->order->id,
        'product_id'  => $this->wrong->product_id,
        'person_id'   => $this->wrong->person_id ?? Person::factory()->create()->id,
        'quantity'    => 1,
        'total_price' => 450,
        'status'      => OrderItemStatus::INCORRECT->value,
    ])->assertSessionHasNoErrors();

    expect($this->wrong->fresh()->status)->toBe(OrderItemStatus::INCORRECT);
});
