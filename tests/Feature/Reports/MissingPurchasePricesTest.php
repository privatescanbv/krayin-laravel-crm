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

test('lists only orders with won plannable items without purchase price', function () {
    wonItem('HIT-1', 'MRI scanner');
    wonItem('MISS-1', 'Other');

    $this->get(route('admin.reports.missing-purchase-prices.index'))
        ->assertOk()
        ->assertSee('HIT-1')
        ->assertDontSee('MISS-1');
});

test('filter switches between plannable, non plannable and all', function () {
    wonItem('AAA-1', 'MRI scanner');
    wonItem('BBB-1', 'Other');

    $url = route('admin.reports.missing-purchase-prices.index');

    $this->get($url.'?planbaar=nee')->assertSee('BBB-1')->assertDontSee('AAA-1');
    $this->get($url.'?planbaar=alles')->assertSee('BBB-1')->assertSee('AAA-1');
});

test('excludes order items that have a purchase price', function () {
    wonItem('PRICED-1', 'MRI scanner')
        ->purchasePrice()->create(['type' => 'main', 'purchase_price_radiology' => 172]);

    $this->get(route('admin.reports.missing-purchase-prices.index').'?planbaar=alles')
        ->assertOk()
        ->assertDontSee('PRICED-1');
});

test('excludes order items whose product has no partner product', function () {
    wonItem('NOPARTNER-1', 'Other', withPartnerProduct: false);
    wonItem('PARTNER-1', 'Other');

    $this->get(route('admin.reports.missing-purchase-prices.index').'?planbaar=alles')
        ->assertOk()
        ->assertSee('PARTNER-1')
        ->assertDontSee('NOPARTNER-1');
});
