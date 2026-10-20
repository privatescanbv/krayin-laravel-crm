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

test('migration restores lost revenue in total_price and keeps active orders on their non-LOST lines', function () {
    $lost = Order::factory()->create(['pipeline_stage_id' => PipelineStage::ORDER_VERLOREN->id()]);
    $allLinesLost = Order::factory()->create(['pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id()]);
    $partlyLost = Order::factory()->create(['pipeline_stage_id' => PipelineStage::ORDER_GEWONNEN->id()]);
    $noLines = Order::factory()->create(['total_price' => 123]);

    OrderItem::factory()->create(['order_id' => $lost->id, 'total_price' => 250, 'status' => OrderItemStatus::LOST->value]);
    OrderItem::factory()->create(['order_id' => $lost->id, 'total_price' => 150, 'status' => OrderItemStatus::LOST->value]);
    OrderItem::factory()->create(['order_id' => $allLinesLost->id, 'total_price' => 750, 'status' => OrderItemStatus::LOST->value]);
    OrderItem::factory()->create(['order_id' => $allLinesLost->id, 'total_price' => 59, 'status' => OrderItemStatus::LOST->value]);
    OrderItem::factory()->create(['order_id' => $partlyLost->id, 'total_price' => 850, 'status' => OrderItemStatus::WON->value]);
    OrderItem::factory()->create(['order_id' => $partlyLost->id, 'total_price' => 450, 'status' => OrderItemStatus::LOST->value]);

    // Old behaviour: total_price only counted non-LOST lines.
    DB::table('orders')->whereIn('id', [$lost->id, $allLinesLost->id])->update(['total_price' => 0]);
    DB::table('orders')->where('id', $partlyLost->id)->update(['total_price' => 850]);
    DB::table('orders')->where('id', $noLines->id)->update(['total_price' => 123]);

    (require database_path('migrations/2026_10_20_140000_recalculate_order_total_price_including_lost_lines.php'))->up();

    $total = fn (Order $order) => (float) DB::table('orders')->where('id', $order->id)->value('total_price');

    expect($total($lost))->toBe(400.0)
        ->and($total($allLinesLost))->toBe(809.0)
        ->and($total($partlyLost))->toBe(850.0)
        ->and($total($noLines))->toBe(123.0);
});
