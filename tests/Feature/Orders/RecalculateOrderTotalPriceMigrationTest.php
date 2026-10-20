<?php

use App\Enums\OrderItemStatus;
use App\Enums\PipelineStage;
use App\Models\Order;
use App\Models\OrderItem;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

test('migration sets total_price to the LOST lines for lost orders and to the active lines otherwise', function () {
    $lost = Order::factory()->create(['pipeline_stage_id' => PipelineStage::ORDER_VERLOREN->id()]);
    $allLinesLost = Order::factory()->create(['pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id()]);
    $partlyLost = Order::factory()->create(['pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id()]);
    $noLines = Order::factory()->create(['total_price' => 123]);

    OrderItem::factory()->create(['order_id' => $lost->id, 'total_price' => 250, 'status' => OrderItemStatus::LOST->value]);
    OrderItem::factory()->create(['order_id' => $lost->id, 'total_price' => 150, 'status' => OrderItemStatus::LOST->value]);
    OrderItem::factory()->create(['order_id' => $lost->id, 'total_price' => 999, 'status' => OrderItemStatus::INCORRECT->value]);
    OrderItem::factory()->create(['order_id' => $allLinesLost->id, 'total_price' => 750, 'status' => OrderItemStatus::LOST->value]);
    OrderItem::factory()->create(['order_id' => $partlyLost->id, 'total_price' => 850, 'status' => OrderItemStatus::WON->value]);
    OrderItem::factory()->create(['order_id' => $partlyLost->id, 'total_price' => 450, 'status' => OrderItemStatus::LOST->value]);
    OrderItem::factory()->create(['order_id' => $partlyLost->id, 'total_price' => 300, 'status' => OrderItemStatus::INCORRECT->value]);

    // Wrong totals as left by the old behaviour.
    DB::table('orders')->where('id', $lost->id)->update(['total_price' => 0]);
    DB::table('orders')->where('id', $allLinesLost->id)->update(['total_price' => 750]);
    DB::table('orders')->where('id', $partlyLost->id)->update(['total_price' => 1600]);
    DB::table('orders')->where('id', $noLines->id)->update(['total_price' => 123]);

    (require database_path('migrations/2026_10_20_140000_recalculate_order_total_price.php'))->up();

    $total = fn (Order $order) => (float) DB::table('orders')->where('id', $order->id)->value('total_price');

    expect($total($lost))->toBe(400.0)
        ->and($total($allLinesLost))->toBe(0.0)
        ->and($total($partlyLost))->toBe(850.0)
        ->and($total($noLines))->toBe(123.0);
});
