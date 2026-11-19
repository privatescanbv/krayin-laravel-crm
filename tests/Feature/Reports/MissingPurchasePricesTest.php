<?php

use App\Enums\OrderItemStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\ResourceType;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Webkul\Product\Models\Product;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    $role = Role::factory()->create(['permission_type' => 'all', 'permissions' => null]);
    $this->actingAs(User::factory()->create(['status' => 1, 'role_id' => $role->id]), 'user');
    $this->withoutMiddleware(Authenticate::class);
});

function wonItem(string $orderNumber, string $resourceType, bool $withPartnerProduct = true): OrderItem
{
    $order = Order::factory()->create(['order_number' => $orderNumber]);
    $product = Product::factory()->create();

    if ($withPartnerProduct) {
        // factory seeds default purchase prices; remove them so none is copied onto the order item
        PartnerProduct::factory()->create(['product_id' => $product->id, 'active' => true])->purchasePrice()->delete();
    }

    return OrderItem::factory()->create([
        'order_id'         => $order->id,
        'product_id'       => $product->id,
        'resource_type_id' => ResourceType::firstOrCreate(['name' => $resourceType])->id,
        'status'           => OrderItemStatus::WON,
    ]);
}

/**
 * Order numbers returned by the report datagrid (same ajax request the table makes).
 *
 * @param  array<string, mixed>  $query
 * @return array<int, string>
 */
function reportOrderNumbers(array $query = []): array
{
    $response = test()->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson(route('admin.reports.missing-purchase-prices.index', $query))
        ->assertOk();

    return collect($response->json('records'))->pluck('order_number')->sort()->values()->all();
}

test('lists only orders with won plannable items without purchase price', function () {
    wonItem('HIT-1', 'MRI scanner');
    wonItem('MISS-1', 'Other');

    expect(reportOrderNumbers())->toBe(['HIT-1']);
});

test('page renders the datagrid', function () {
    $this->get(route('admin.reports.missing-purchase-prices.index'))->assertOk();
});

test('plannable column filter switches between plannable, non plannable and all', function () {
    wonItem('AAA-1', 'MRI scanner');
    wonItem('BBB-1', 'Other');

    expect(reportOrderNumbers(['filters' => ['plannable' => ['Nee']]]))->toBe(['BBB-1'])
        ->and(reportOrderNumbers(['filters' => ['plannable' => ['Ja', 'Nee']]]))->toBe(['AAA-1', 'BBB-1']);
});

test('excludes order items that have a purchase price', function () {
    wonItem('PRICED-1', 'MRI scanner')
        ->purchasePrice()->create(['type' => 'main', 'purchase_price_radiology' => 172]);

    expect(reportOrderNumbers(['filters' => ['plannable' => ['Ja', 'Nee']]]))->toBe([]);
});

test('excludes order items whose product has no partner product', function () {
    wonItem('NOPARTNER-1', 'Other', withPartnerProduct: false);
    wonItem('PARTNER-1', 'Other');

    expect(reportOrderNumbers(['filters' => ['plannable' => ['Ja', 'Nee']]]))->toBe(['PARTNER-1']);
});

test('sugar filter splits orders on external id and defaults to no import', function () {
    wonItem('SGR-1', 'MRI scanner')->order->update(['external_id' => 'abc']);
    wonItem('LOC-1', 'MRI scanner');

    expect(reportOrderNumbers())->toBe(['LOC-1'])
        ->and(reportOrderNumbers(['filters' => ['sugar_import' => ['Ja']]]))->toBe(['SGR-1'])
        ->and(reportOrderNumbers(['filters' => ['sugar_import' => ['Nee']]]))->toBe(['LOC-1'])
        ->and(reportOrderNumbers(['filters' => ['sugar_import' => ['Ja', 'Nee']]]))->toBe(['LOC-1', 'SGR-1']);
});

test('exposes department, total sales and total purchase per order', function () {
    $item = wonItem('TOT-1', 'MRI scanner');
    $item->order->update(['total_price' => 1234.5]);

    $row = test()->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson(route('admin.reports.missing-purchase-prices.index'))
        ->json('records.0');

    expect($row['department'])->toBe('Privatescan')
        ->and($row['plannable'])->toBe('Ja')
        ->and($row['sugar_import'])->toBe('Nee')
        ->and($row['total_price'])->toBe('€ 1.234,50')
        ->and($row['total_purchase'])->toBe('€ 0,00');
});

test('filters on a from-to range of total sales and total purchase', function () {
    wonItem('LOW-1', 'MRI scanner')->order->update(['total_price' => 100]);
    wonItem('MID-1', 'MRI scanner')->order->update(['total_price' => 500]);
    wonItem('HIGH-1', 'MRI scanner')->order->update(['total_price' => 900]);

    $range = fn (string $column, mixed $from, mixed $to) => ['filters' => [$column => [[$from, $to]]]];

    expect(reportOrderNumbers($range('total_price', 200, 600)))->toBe(['MID-1'])
        ->and(reportOrderNumbers($range('total_price', 200, '')))->toBe(['HIGH-1', 'MID-1'])
        ->and(reportOrderNumbers($range('total_price', '', 200)))->toBe(['LOW-1'])
        // all three orders have 0 purchase total (their line has no price)
        ->and(reportOrderNumbers($range('total_purchase', 1, '')))->toBe([])
        ->and(reportOrderNumbers($range('total_purchase', 0, '')))->toBe(['HIGH-1', 'LOW-1', 'MID-1'])
        ->and(reportOrderNumbers($range('total_purchase', '', 0)))->toBe(['HIGH-1', 'LOW-1', 'MID-1'])
        ->and(reportOrderNumbers($range('total_purchase', 0, 0)))->toBe(['HIGH-1', 'LOW-1', 'MID-1']);
});

test('filters on department', function () {
    wonItem('DEP-1', 'MRI scanner');

    expect(reportOrderNumbers(['filters' => ['department' => ['Privatescan']]]))->toBe(['DEP-1'])
        ->and(reportOrderNumbers(['filters' => ['department' => ['Herniapoli']]]))->toBe([]);
});
