<?php

namespace Tests\Feature\Orders;

use App\Enums\PurchasePriceType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\ResourceType;
use App\Services\OrderCheckService;
use App\Services\OrderItemPurchasePriceService;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Contact\Models\Person;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Product\Models\Product;

uses(RefreshDatabase::class);

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);
    test()->seed(TestSeeder::class);
    test()->actingAs(makeUser(), 'user');
    test()->person = Person::factory()->create();
});

/**
 * Non-plannable products (resource type "Other") never get booked onto a resource, so they
 * never go through ResourceOrderItemObserver. MBS-515: their purchase price must instead be
 * resolved directly off product_id when the product gets linked to the order item.
 */
function nonPlannableProduct(): Product
{
    $resourceType = ResourceType::firstOrCreate(['name' => 'Other'], ['description' => null]);

    return Product::factory()->create(['resource_type_id' => $resourceType->id]);
}

test('creating an order item with a non-plannable product auto-links the single partner product and copies its price', function () {
    $product = nonPlannableProduct();

    $partnerProduct = PartnerProduct::factory()
        ->withMainPurchasePrice(['misc' => 30, 'total' => 30])
        ->create(['product_id' => $product->id]);

    $order = Order::factory()->create();
    $item = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'person_id'  => test()->person->id,
    ]);

    $item->refresh();

    expect($item->partner_product_id)->toBe($partnerProduct->id)
        ->and((float) $item->purchasePrice->purchase_price)->toBe(30.0);
});

test('non-plannable product without any matching partner product leaves purchase price empty without a warning', function () {
    // No partner product at all for this product is the normal case (plenty of products have
    // no purchase/partner arrangement), so this must stay silent — unlike the ambiguous-match
    // case below, which is a genuine configuration problem worth flagging.
    $product = nonPlannableProduct();

    $order = Order::factory()->create();
    $item = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'person_id'  => test()->person->id,
    ]);

    $item->refresh();

    expect($item->partner_product_id)->toBeNull()
        ->and($item->purchasePrice()->exists())->toBeFalse()
        ->and($order->orderChecks()->where('name', 'like', 'Inkoopprijs ontbreekt%')->exists())->toBeFalse();
});

test('non-plannable product with multiple matching partner products leaves purchase price empty and flags a warning', function () {
    $product = nonPlannableProduct();

    PartnerProduct::factory()->withMainPurchasePrice(['misc' => 10, 'total' => 10])->create(['product_id' => $product->id]);
    PartnerProduct::factory()->withMainPurchasePrice(['misc' => 20, 'total' => 20])->create(['product_id' => $product->id]);

    $order = Order::factory()->create();
    $item = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'person_id'  => test()->person->id,
    ]);

    $item->refresh();

    expect($item->partner_product_id)->toBeNull()
        ->and($item->purchasePrice()->exists())->toBeFalse();

    expect($order->orderChecks()->where('name', 'like', 'Inkoopprijs ontbreekt%')->exists())->toBeTrue();
});

test('non-plannable product does not overwrite a manually entered purchase price', function () {
    $product = nonPlannableProduct();

    PartnerProduct::factory()
        ->withMainPurchasePrice(['misc' => 999, 'total' => 999])
        ->create(['product_id' => $product->id]);

    $order = Order::factory()->create();
    $item = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'person_id'  => test()->person->id,
    ]);
    $item->purchasePrice()->updateOrCreate([], [
        'type'                => PurchasePriceType::MAIN,
        'purchase_price_misc' => 42,
        'purchase_price'      => 42,
    ]);

    // Re-trigger resolution the same way a product change would.
    app(OrderItemPurchasePriceService::class)->relinkPartnerProduct($item->fresh());

    $item->refresh();

    expect((float) $item->purchasePrice->purchase_price)->toBe(42.0);
});

test('switching an order item to a non-plannable product resolves its purchase price', function () {
    $oldProduct = Product::factory()->create();
    $order = Order::factory()->create();
    $item = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $oldProduct->id,
        'person_id'  => test()->person->id,
    ]);

    $newProduct = nonPlannableProduct();
    $partnerProduct = PartnerProduct::factory()
        ->withMainPurchasePrice(['misc' => 15, 'total' => 15])
        ->create(['product_id' => $newProduct->id]);

    $item->update(['product_id' => $newProduct->id]);
    $item->refresh();

    expect($item->partner_product_id)->toBe($partnerProduct->id)
        ->and((float) $item->purchasePrice->purchase_price)->toBe(15.0);
});

test('resolving an ambiguous match successfully clears a previously flagged warning', function () {
    $product = nonPlannableProduct();

    $keep = PartnerProduct::factory()->withMainPurchasePrice(['misc' => 5, 'total' => 5])->create(['product_id' => $product->id]);
    $duplicate = PartnerProduct::factory()->withMainPurchasePrice(['misc' => 7, 'total' => 7])->create(['product_id' => $product->id]);

    $order = Order::factory()->create();
    $item = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'person_id'  => test()->person->id,
    ]);

    expect($order->orderChecks()->where('name', 'like', 'Inkoopprijs ontbreekt%')->exists())->toBeTrue();

    $duplicate->delete();

    app(OrderItemPurchasePriceService::class)->relinkPartnerProduct($item->fresh());

    expect($order->orderChecks()->where('name', 'like', 'Inkoopprijs ontbreekt%')->exists())->toBeFalse()
        ->and($item->fresh()->partner_product_id)->toBe($keep->id);
});

function runBackfillMigration(): void
{
    (require database_path('migrations/2026_11_03_120000_backfill_non_plannable_order_item_purchase_prices.php'))->up();
}

test('backfill migration prices a line that is already linked to a partner product but has no purchase price', function () {
    $product = nonPlannableProduct();
    $partnerProduct = PartnerProduct::factory()
        ->withMainPurchasePrice(['misc' => 30, 'total' => 30])
        ->create(['product_id' => $product->id]);

    $item = OrderItem::factory()->create(['order_id' => Order::factory()->create()->id, 'product_id' => $product->id, 'person_id' => test()->person->id]);
    $item->purchasePrice()->delete();
    $item->update(['partner_product_id' => $partnerProduct->id]);

    runBackfillMigration();

    expect((float) $item->fresh()->purchasePrice->purchase_price)->toBe(30.0)
        ->and($item->fresh()->partner_product_id)->toBe($partnerProduct->id);
});

test('backfill migration keeps the existing link even when several partner products match', function () {
    $product = nonPlannableProduct();
    $linked = PartnerProduct::factory()->withMainPurchasePrice(['misc' => 12, 'total' => 12])->create(['product_id' => $product->id]);
    PartnerProduct::factory()->withMainPurchasePrice(['misc' => 99, 'total' => 99])->create(['product_id' => $product->id]);

    $item = OrderItem::factory()->create(['order_id' => Order::factory()->create()->id, 'product_id' => $product->id, 'person_id' => test()->person->id]);
    $item->purchasePrice()->delete();
    $item->update(['partner_product_id' => $linked->id]);

    runBackfillMigration();

    expect((float) $item->fresh()->purchasePrice->purchase_price)->toBe(12.0)
        ->and($item->fresh()->partner_product_id)->toBe($linked->id);
});

test('backfill migration leaves a line that already has a purchase price untouched', function () {
    $product = nonPlannableProduct();
    $partnerProduct = PartnerProduct::factory()->withMainPurchasePrice(['misc' => 30, 'total' => 30])->create(['product_id' => $product->id]);

    $item = OrderItem::factory()->create(['order_id' => Order::factory()->create()->id, 'product_id' => $product->id, 'person_id' => test()->person->id]);
    $item->purchasePrice()->updateOrCreate([], ['type' => PurchasePriceType::MAIN, 'purchase_price_misc' => 45, 'purchase_price' => 45]);
    $item->update(['partner_product_id' => $partnerProduct->id]);

    runBackfillMigration();

    expect((float) $item->fresh()->purchasePrice->purchase_price)->toBe(45.0);
});

test('multiple matching partner products do not flag a warning when a purchase price is already entered', function () {
    $product = nonPlannableProduct();
    PartnerProduct::factory()->count(2)->withMainPurchasePrice(['misc' => 30, 'total' => 30])->create(['product_id' => $product->id]);

    $item = OrderItem::factory()->create(['order_id' => Order::factory()->create()->id, 'product_id' => $product->id, 'person_id' => test()->person->id]);
    $item->purchasePrice()->updateOrCreate([], ['type' => PurchasePriceType::MAIN, 'purchase_price_misc' => 45, 'purchase_price' => 45]);

    app(OrderItemPurchasePriceService::class)->relinkPartnerProduct($item->fresh());

    expect((float) $item->fresh()->purchasePrice->purchase_price)->toBe(45.0)
        ->and($item->order->orderChecks()->where('name', 'like', 'Inkoopprijs ontbreekt%')->exists())->toBeFalse();
});

test('clearing the warning of one line does not clear the warning of a line whose id starts with the same digits', function () {
    $order = Order::factory()->create();
    $itemA = OrderItem::factory()->create(['order_id' => $order->id, 'person_id' => test()->person->id]);
    $itemB = OrderItem::factory()->create(['order_id' => $order->id, 'person_id' => test()->person->id]);
    $itemB->id = (int) ($itemA->id.'3');
    $service = app(OrderCheckService::class);

    $service->flagPurchasePriceIssue($itemA, 'reden');
    $service->flagPurchasePriceIssue($itemB, 'reden');
    $service->clearPurchasePriceIssue($itemA);

    $names = $order->orderChecks()->pluck('name')->all();
    expect($names)->toHaveCount(1)->and($names[0])->toContain("#{$itemB->id}:");
});

test('deleting an order item removes its purchase price warning', function () {
    $product = nonPlannableProduct();
    PartnerProduct::factory()->count(2)->withMainPurchasePrice(['misc' => 30, 'total' => 30])->create(['product_id' => $product->id]);
    $order = Order::factory()->create();
    $item = OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'person_id' => test()->person->id]);

    expect($order->orderChecks()->where('name', 'like', 'Inkoopprijs ontbreekt%')->exists())->toBeTrue();

    $item->delete();

    expect($order->orderChecks()->where('name', 'like', 'Inkoopprijs ontbreekt%')->exists())->toBeFalse();
});
