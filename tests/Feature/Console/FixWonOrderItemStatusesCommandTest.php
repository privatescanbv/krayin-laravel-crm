<?php

use App\Console\Commands\FixWonOrderItemStatuses;
use App\Enums\OrderItemStatus;
use App\Enums\PipelineStage;
use App\Models\Order;
use App\Models\OrderItem;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

test('dry run lists incorrect items on won orders without persisting changes', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id(),
    ]);

    $newItem = OrderItem::factory()->create([
        'order_id' => $order->id,
        'status'   => OrderItemStatus::NEW->value,
    ]);
    $plannedItem = OrderItem::factory()->create([
        'order_id' => $order->id,
        'status'   => OrderItemStatus::PLANNED->value,
    ]);
    $wonItem = OrderItem::factory()->create([
        'order_id' => $order->id,
        'status'   => OrderItemStatus::WON->value,
    ]);
    $lostItem = OrderItem::factory()->create([
        'order_id' => $order->id,
        'status'   => OrderItemStatus::LOST->value,
    ]);

    $exitCode = Artisan::call(FixWonOrderItemStatuses::class, ['--dry-run' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Order item ID')
        ->and($output)->toContain((string) $newItem->id)
        ->and($output)->toContain((string) $plannedItem->id)
        ->and($output)->toContain('zouden naar won worden gezet')
        ->and($output)->toContain('zonder --dry-run')
        ->and($newItem->fresh()->status)->toBe(OrderItemStatus::NEW)
        ->and($plannedItem->fresh()->status)->toBe(OrderItemStatus::PLANNED)
        ->and($wonItem->fresh()->status)->toBe(OrderItemStatus::WON)
        ->and($lostItem->fresh()->status)->toBe(OrderItemStatus::LOST);
});

test('command updates non won and non lost items on won orders to won', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id(),
    ]);

    $newItem = OrderItem::factory()->create([
        'order_id' => $order->id,
        'status'   => OrderItemStatus::NEW->value,
    ]);
    $plannedItem = OrderItem::factory()->create([
        'order_id' => $order->id,
        'status'   => OrderItemStatus::PLANNED->value,
    ]);
    $wonItem = OrderItem::factory()->create([
        'order_id' => $order->id,
        'status'   => OrderItemStatus::WON->value,
    ]);
    $lostItem = OrderItem::factory()->create([
        'order_id' => $order->id,
        'status'   => OrderItemStatus::LOST->value,
    ]);

    $this->artisan(FixWonOrderItemStatuses::class)
        ->expectsOutputToContain('bijgewerkt naar won')
        ->assertSuccessful();

    expect($newItem->fresh()->status)->toBe(OrderItemStatus::WON)
        ->and($plannedItem->fresh()->status)->toBe(OrderItemStatus::WON)
        ->and($wonItem->fresh()->status)->toBe(OrderItemStatus::WON)
        ->and($lostItem->fresh()->status)->toBe(OrderItemStatus::LOST);
});

test('command also fixes hernia won orders', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN_HERNIA->id(),
    ]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'status'   => OrderItemStatus::NEW->value,
    ]);

    $this->artisan(FixWonOrderItemStatuses::class)->assertSuccessful();

    expect($item->fresh()->status)->toBe(OrderItemStatus::WON);
});

test('command ignores orders that are not won', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_UITGEVOERD->id(),
    ]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'status'   => OrderItemStatus::NEW->value,
    ]);

    $this->artisan(FixWonOrderItemStatuses::class)
        ->expectsOutputToContain('Geen gewonnen orders gevonden')
        ->assertSuccessful();

    expect($item->fresh()->status)->toBe(OrderItemStatus::NEW);
});

test('command can be limited to specific order ids', function () {
    $target = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id(),
    ]);
    $other = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id(),
    ]);

    $targetItem = OrderItem::factory()->create([
        'order_id' => $target->id,
        'status'   => OrderItemStatus::NEW->value,
    ]);
    $otherItem = OrderItem::factory()->create([
        'order_id' => $other->id,
        'status'   => OrderItemStatus::NEW->value,
    ]);

    $this->artisan(FixWonOrderItemStatuses::class, ['--order-id' => [$target->id]])
        ->assertSuccessful();

    expect($targetItem->fresh()->status)->toBe(OrderItemStatus::WON)
        ->and($otherItem->fresh()->status)->toBe(OrderItemStatus::NEW);
});
