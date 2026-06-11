<?php

use App\Enums\LostReason;
use App\Enums\OrderItemStatus;
use App\Enums\PipelineStage;
use App\Enums\ResourceType as ResourceTypeEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\ResourceOrderItem;
use App\Models\ResourceType;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Webkul\Contact\Models\Organization;
use Webkul\Lead\Models\Stage;
use Webkul\Product\Models\Product;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

test('updating to a won stage marks all non-lost order items as won', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
    ]);

    $newItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::NEW->value]);
    $plannedItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::PLANNED->value]);
    $lostItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::LOST->value]);

    $order->pipeline_stage_id = PipelineStage::ORDER_UITGEVOERD->id();
    $order->save();

    expect($newItem->fresh()->status)->toBe(OrderItemStatus::WON)
        ->and($plannedItem->fresh()->status)->toBe(OrderItemStatus::WON)
        ->and($lostItem->fresh()->status)->toBe(OrderItemStatus::LOST);
});

test('updating to a hernia won stage marks order items as won', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_VOORBEREIDEN_HERNIA->id(),
    ]);

    $newItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::NEW->value]);
    $plannedItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::PLANNED->value]);
    $lostItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::LOST->value]);

    $order->pipeline_stage_id = PipelineStage::ORDER_UITGEVOERD_HERNIA->id();
    $order->save();

    expect($newItem->fresh()->status)->toBe(OrderItemStatus::WON)
        ->and($plannedItem->fresh()->status)->toBe(OrderItemStatus::WON)
        ->and($lostItem->fresh()->status)->toBe(OrderItemStatus::LOST);
});

test('updating to a non-won stage does not change order item status', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
    ]);

    $newItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::NEW->value]);
    $plannedItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::PLANNED->value]);
    $lostItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::LOST->value]);

    $order->pipeline_stage_id = PipelineStage::ORDER_INGEPLAND->id();
    $order->save();

    expect($newItem->fresh()->status)->toBe(OrderItemStatus::NEW)
        ->and($plannedItem->fresh()->status)->toBe(OrderItemStatus::PLANNED)
        ->and($lostItem->fresh()->status)->toBe(OrderItemStatus::LOST);
});

test('order items from other orders are not affected when one order moves to won stage', function () {
    $orderA = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
    ]);
    $orderB = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
    ]);

    $itemA = OrderItem::factory()->create(['order_id' => $orderA->id, 'status' => OrderItemStatus::PLANNED->value]);
    $itemB = OrderItem::factory()->create(['order_id' => $orderB->id, 'status' => OrderItemStatus::NEW->value]);

    $orderA->pipeline_stage_id = PipelineStage::ORDER_RAPPORTEN_ONTVANGEN->id();
    $orderA->save();

    expect($itemA->fresh()->status)->toBe(OrderItemStatus::WON)
        ->and($itemB->fresh()->status)->toBe(OrderItemStatus::NEW);
});

test('updating to verloren stage marks all order items as lost', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id(),
    ]);

    $newItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::NEW->value]);
    $plannedItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::PLANNED->value]);
    $wonItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::WON->value]);

    $order->pipeline_stage_id = PipelineStage::ORDER_VERLOREN->id();
    $order->save();

    expect($newItem->fresh()->status)->toBe(OrderItemStatus::LOST)
        ->and($plannedItem->fresh()->status)->toBe(OrderItemStatus::LOST)
        ->and($wonItem->fresh()->status)->toBe(OrderItemStatus::LOST);
});

test('updating to verloren stage removes resource_orderitem planning slots', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id(),
    ]);

    $item = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::PLANNED->value]);
    ResourceOrderItem::factory()->create([
        'orderitem_id' => $item->id,
        'from'         => now()->toDateTimeString(),
        'to'           => now()->addHour()->toDateTimeString(),
    ]);

    expect(DB::table('resource_orderitem')->where('orderitem_id', $item->id)->count())->toBe(1);

    $order->pipeline_stage_id = PipelineStage::ORDER_VERLOREN->id();
    $order->save();

    expect(DB::table('resource_orderitem')->where('orderitem_id', $item->id)->count())->toBe(0);
});

test('updating to hernia verloren stage marks all order items as lost', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND_HERNIA->id(),
    ]);

    $plannedItem = OrderItem::factory()->create(['order_id' => $order->id, 'status' => OrderItemStatus::PLANNED->value]);

    $order->pipeline_stage_id = PipelineStage::ORDER_VERLOREN_HERNIA->id();
    $order->save();

    expect($plannedItem->fresh()->status)->toBe(OrderItemStatus::LOST);
});

test('items from other orders are not affected when one order moves to verloren', function () {
    $orderA = Order::factory()->create(['pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id()]);
    $orderB = Order::factory()->create(['pipeline_stage_id' => PipelineStage::ORDER_INGEPLAND->id()]);

    $itemA = OrderItem::factory()->create(['order_id' => $orderA->id, 'status' => OrderItemStatus::PLANNED->value]);
    $itemB = OrderItem::factory()->create(['order_id' => $orderB->id, 'status' => OrderItemStatus::PLANNED->value]);

    $orderA->pipeline_stage_id = PipelineStage::ORDER_VERLOREN->id();
    $orderA->save();

    expect($itemA->fresh()->status)->toBe(OrderItemStatus::LOST)
        ->and($itemB->fresh()->status)->toBe(OrderItemStatus::PLANNED);
});

test('created dispatches order.update_stage.after event', function () {
    Event::fake(['order.update_stage.after']);

    Order::factory()->create();

    Event::assertDispatched('order.update_stage.after');
});

test('updating to a won stage dispatches order.update_stage.after event', function () {
    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
    ]);

    Event::fake(['order.update_stage.after']);

    $order->pipeline_stage_id = PipelineStage::ORDER_BEVESTIGD->id();
    $order->save();

    Event::assertDispatched('order.update_stage.after');
});

// ---------------------------------------------------------------------------
// logFieldChanges audit trail
// ---------------------------------------------------------------------------

test('updating title creates a system activity log', function () {
    $order = Order::factory()->create(['title' => 'Oud titel']);

    $order->update(['title' => 'Nieuw titel']);

    $activity = DB::table('activities')
        ->where('order_id', $order->id)
        ->where('type', 'system')
        ->where('title', 'Titel gewijzigd')
        ->first();

    expect($activity)->not->toBeNull();

    $additional = json_decode($activity->additional, true);
    expect($additional['old']['label'])->toBe('Oud titel')
        ->and($additional['new']['label'])->toBe('Nieuw titel');
});

test('updating pipeline_stage_id logs stage names as labels', function () {
    $stageA = Stage::where('code', PipelineStage::ORDER_CONFIRM->value)->firstOrFail();
    $stageB = Stage::where('code', PipelineStage::ORDER_INGEPLAND->value)->firstOrFail();

    $order = Order::factory()->create(['pipeline_stage_id' => $stageA->id]);

    $order->update(['pipeline_stage_id' => $stageB->id]);

    $activity = DB::table('activities')
        ->where('order_id', $order->id)
        ->where('type', 'system')
        ->where('title', 'Status gewijzigd')
        ->first();

    expect($activity)->not->toBeNull();

    $additional = json_decode($activity->additional, true);
    expect($additional['old']['label'])->toBe($stageA->name)
        ->and($additional['new']['label'])->toBe($stageB->name);
});

test('updating user_id logs user names as labels', function () {
    $userA = makeUser();
    $userB = makeUser();

    $order = Order::factory()->create(['user_id' => $userA->id]);

    $order->update(['user_id' => $userB->id]);

    $activity = DB::table('activities')
        ->where('order_id', $order->id)
        ->where('type', 'system')
        ->where('title', 'Toegewezen aan gewijzigd')
        ->first();

    expect($activity)->not->toBeNull();

    $additional = json_decode($activity->additional, true);
    expect($additional['old']['label'])->toBe($userA->name)
        ->and($additional['new']['label'])->toBe($userB->name);
});

test('updating total_price logs formatted euro amounts', function () {
    $order = Order::factory()->create(['total_price' => 100.00]);

    $order->update(['total_price' => 250.50]);

    $activity = DB::table('activities')
        ->where('order_id', $order->id)
        ->where('type', 'system')
        ->where('title', 'Totaalprijs gewijzigd')
        ->first();

    expect($activity)->not->toBeNull();

    $additional = json_decode($activity->additional, true);
    expect($additional['old']['label'])->toBe('€ 100.00')
        ->and($additional['new']['label'])->toBe('€ 250.50');
});

test('updating first_examination_at logs formatted dates', function () {
    $order = Order::factory()->create(['first_examination_at' => '2026-03-01']);

    $order->update(['first_examination_at' => '2026-06-15']);

    $activity = DB::table('activities')
        ->where('order_id', $order->id)
        ->where('type', 'system')
        ->where('title', 'Eerste onderzoeksdatum gewijzigd')
        ->first();

    expect($activity)->not->toBeNull();

    $additional = json_decode($activity->additional, true);
    expect($additional['old']['label'])->toBe('01-03-2026')
        ->and($additional['new']['label'])->toBe('15-06-2026');
});

test('updating lost_reason logs enum labels', function () {
    $order = Order::factory()->create(['lost_reason' => null]);

    $order->update(['lost_reason' => LostReason::Price]);

    $activity = DB::table('activities')
        ->where('order_id', $order->id)
        ->where('type', 'system')
        ->where('title', 'Reden verlies gewijzigd')
        ->first();

    expect($activity)->not->toBeNull();

    $additional = json_decode($activity->additional, true);
    expect($additional['new']['label'])->toBe(LostReason::Price->label());
});

test('updating is_business logs ja nee labels', function () {
    $order = Order::factory()->create(['is_business' => false]);

    $order->update(['is_business' => true]);

    $activity = DB::table('activities')
        ->where('order_id', $order->id)
        ->where('type', 'system')
        ->where('title', 'Zakelijk gewijzigd')
        ->first();

    expect($activity)->not->toBeNull();

    $additional = json_decode($activity->additional, true);
    expect($additional['old']['label'])->toBe('Nee')
        ->and($additional['new']['label'])->toBe('Ja');
});

test('updating organization_id logs organization name', function () {
    $org = Organization::factory()->create(['name' => 'Test Organisatie BV']);
    $order = Order::factory()->create(['organization_id' => null]);

    $order->update(['organization_id' => $org->id]);

    $activity = DB::table('activities')
        ->where('order_id', $order->id)
        ->where('type', 'system')
        ->where('title', 'Organisatie gewijzigd')
        ->first();

    expect($activity)->not->toBeNull();

    $additional = json_decode($activity->additional, true);
    expect($additional['new']['label'])->toBe('Test Organisatie BV');
});

test('unchanged fields do not create activity log entries', function () {
    $order = Order::factory()->create(['title' => 'Zelfde titel']);

    $countBefore = DB::table('activities')->where('order_id', $order->id)->where('type', 'system')->count();

    $order->update(['title' => 'Zelfde titel']);

    $countAfter = DB::table('activities')->where('order_id', $order->id)->where('type', 'system')->count();

    expect($countAfter)->toBe($countBefore);
});

test('saveQuietly does not create activity log entries', function () {
    $order = Order::factory()->create(['title' => 'Origineel']);

    $countBefore = DB::table('activities')->where('order_id', $order->id)->where('type', 'system')->count();

    $order->title = 'Stille update';
    $order->saveQuietly();

    $countAfter = DB::table('activities')->where('order_id', $order->id)->where('type', 'system')->count();

    expect($countAfter)->toBe($countBefore);
});

test('skipped fields (order_number, invoice_number) do not create activity entries', function () {
    $order = Order::factory()->create(['invoice_number' => null]);

    $order->update(['invoice_number' => 'INV-2026-001']);

    $activity = DB::table('activities')
        ->where('order_id', $order->id)
        ->where('type', 'system')
        ->where('title', 'LIKE', '%invoice%')
        ->first();

    expect($activity)->toBeNull();
});

test('touching order does not downgrade from order akkoord when plannable items are WON', function () {
    $plannableResourceType = ResourceType::where('name', ResourceTypeEnum::MRI_SCANNER->label())->firstOrFail();
    $salesLead = SalesLead::factory()->create();
    $productWithPartner = Product::factory()->create();
    PartnerProduct::factory()->create([
        'product_id'       => $productWithPartner->id,
        'resource_type_id' => $plannableResourceType->id,
    ]);

    $order = Order::factory()->create([
        'pipeline_stage_id' => PipelineStage::ORDER_BEVESTIGD->id(),
        'sales_lead_id'     => $salesLead->id,
    ]);

    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $productWithPartner->id,
        'status'     => OrderItemStatus::WON->value,
    ]);

    $order->touch();

    expect($order->fresh()->pipeline_stage_id)->toBe(PipelineStage::ORDER_BEVESTIGD->id());
});
