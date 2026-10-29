<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductType;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Contact\Models\Person;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductGroup;

uses(RefreshDatabase::class);

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);
    test()->seed(TestSeeder::class);
    test()->actingAs(makeUser(), 'user');
});

test('order item accepts a negative total_price', function () {
    $order = Order::factory()->create();
    $product = Product::factory()->create();
    $person = Person::factory()->create();

    $item = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'person_id'  => $person->id,
    ]);

    $payload = [
        'order_id'    => $order->id,
        'product_id'  => $product->id,
        'person_id'   => $person->id,
        'quantity'    => 1,
        'total_price' => -50,
        'status'      => 'new',
        '_method'     => 'put',
    ];

    $response = $this->post(route('admin.order_items.update', ['id' => $item->id]), $payload);
    $response->assertRedirect();

    expect((float) $item->refresh()->total_price)->toBe(-50.0);
});

test('order item still rejects a negative purchase price', function () {
    $order = Order::factory()->create();
    $product = Product::factory()->create();
    $person = Person::factory()->create();

    $item = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'person_id'  => $person->id,
    ]);

    $payload = [
        'order_id'            => $order->id,
        'product_id'          => $product->id,
        'person_id'           => $person->id,
        'quantity'            => 1,
        'total_price'         => 100,
        'status'              => 'new',
        'purchase_price_misc' => -10,
        '_method'             => 'put',
    ];

    $response = $this->post(route('admin.order_items.update', ['id' => $item->id]), $payload);

    $response->assertSessionHasErrors('purchase_price_misc');
});

test('korting product migration seeds a negative-price product without a partner product', function () {
    ProductType::create(['name' => 'Diensten']);
    ProductGroup::create(['name' => 'Administratief']);

    $migration = require database_path('migrations/2026_10_29_000000_seed_korting_product.php');
    $migration->up();

    $product = Product::where('external_id', '1132')->first();

    expect($product)->not->toBeNull()
        ->and($product->name)->toBe('Korting')
        ->and((float) $product->price)->toBe(-50.0)
        ->and($product->partnerProducts()->count())->toBe(0);

    // Running it again must not create a duplicate.
    $migration->up();
    expect(Product::where('external_id', '1132')->count())->toBe(1);

    $migration->down();
    expect(Product::where('external_id', '1132')->exists())->toBeFalse();
});

test('korting product migration corrects the old 1,00 placeholder price but keeps a hand-set price', function () {
    $migration = require database_path('migrations/2026_10_29_000000_seed_korting_product.php');
    $korting = Product::where('external_id', '1132')->first()
        ?? Product::factory()->create(['external_id' => '1132', 'name' => 'Korting']);

    $korting->update(['price' => 1.00]);
    $migration->up();
    expect((float) $korting->refresh()->price)->toBe(-50.0);

    $korting->update(['price' => -25.00]);
    $migration->up();
    expect((float) $korting->refresh()->price)->toBe(-25.0);
});
