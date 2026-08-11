<?php

namespace Tests\Feature\Orders;

use App\Enums\PurchasePriceType;
use App\Models\Clinic;
use App\Models\ClinicDepartment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use App\Models\ResourceType;
use Carbon\Carbon;
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

function createOrderItemWithProduct(): array
{
    $order = Order::factory()->create();
    $product = Product::factory()->create();

    $item = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'person_id'  => test()->person->id,
    ]);

    return [$order, $product, $item];
}

function createPartnerProductForClinic(Product $product, Clinic $clinic, array $priceComponents): PartnerProduct
{
    $partnerProduct = PartnerProduct::factory()->create(['product_id' => $product->id]);
    $partnerProduct->clinics()->sync([$clinic->id]);
    $partnerProduct->purchasePrice->update(array_merge([
        'purchase_price_misc'       => 0,
        'purchase_price_doctor'     => 0,
        'purchase_price_cardiology' => 0,
        'purchase_price_clinic'     => 0,
        'purchase_price_radiology'  => 0,
        'purchase_price'            => 0,
    ], $priceComponents));

    return $partnerProduct;
}

test('edit page shows zero purchase prices when order item has no stored price', function () {
    [$order, $product, $item] = createOrderItemWithProduct();

    PartnerProduct::factory()
        ->withMainPurchasePrice(['misc' => 99, 'total' => 99])
        ->create(['product_id' => $product->id]);

    $response = $this->get(route('admin.order_items.edit', ['id' => $item->id]));

    $response->assertOk();
    $response->assertSee('name="purchase_price_misc"', false);
    $response->assertDontSee('value="99,00"', false);
});

test('update with manual purchase price stores MAIN row', function () {
    [$order, $product, $item] = createOrderItemWithProduct();

    $payload = [
        'order_id'                  => $order->id,
        'product_id'                => $product->id,
        'person_id'                 => test()->person->id,
        'quantity'                  => 1,
        'total_price'               => 100,
        'status'                    => 'new',
        'purchase_price_misc'       => 25,
        'purchase_price_doctor'     => 0,
        'purchase_price_cardiology' => 0,
        'purchase_price_clinic'     => 0,
        'purchase_price_radiology'  => 0,
        '_method'                   => 'put',
    ];

    $response = $this->post(route('admin.order_items.update', ['id' => $item->id]), $payload);
    $response->assertRedirect();

    $item->refresh();
    expect($item->hasEnteredPurchasePrice())->toBeTrue()
        ->and((float) $item->purchasePrice->purchase_price_misc)->toBe(25.0);
});

test('update with all zero purchase prices removes MAIN row', function () {
    [$order, $product, $item] = createOrderItemWithProduct();

    $item->purchasePrice()->create([
        'type'                => PurchasePriceType::MAIN,
        'purchase_price_misc' => 10,
        'purchase_price'      => 10,
    ]);

    $payload = [
        'order_id'                  => $order->id,
        'product_id'                => $product->id,
        'person_id'                 => test()->person->id,
        'quantity'                  => 1,
        'total_price'               => 100,
        'status'                    => 'new',
        'purchase_price_misc'       => 0,
        'purchase_price_doctor'     => 0,
        'purchase_price_cardiology' => 0,
        'purchase_price_clinic'     => 0,
        'purchase_price_radiology'  => 0,
        '_method'                   => 'put',
    ];

    $response = $this->post(route('admin.order_items.update', ['id' => $item->id]), $payload);
    $response->assertRedirect();

    expect($item->fresh()->purchasePrice()->exists())->toBeFalse();
});

test('resolvedPurchasePrice does not sum multiple partner products', function () {
    [$order, $product, $item] = createOrderItemWithProduct();

    PartnerProduct::factory()
        ->withMainPurchasePrice(['misc' => 10, 'total' => 10])
        ->create(['product_id' => $product->id]);
    PartnerProduct::factory()
        ->withMainPurchasePrice(['misc' => 20, 'total' => 20])
        ->create(['product_id' => $product->id]);

    $resolved = $item->fresh()->resolvedPurchasePrice();

    expect((float) $resolved->purchase_price)->toBe(0.0)
        ->and((float) $resolved->purchase_price_misc)->toBe(0.0);
});

test('planning sets partner_product_id and copies price when order item has no entered price', function () {
    $clinic = Clinic::factory()->create();
    $dept = ClinicDepartment::factory()->create(['clinic_id' => $clinic->id]);
    $resourceType = ResourceType::firstOrCreate(['name' => 'MRI scanner'], ['description' => null]);
    $resource = Resource::factory()->create([
        'clinic_department_id' => $dept->id,
        'resource_type_id'     => $resourceType->id,
    ]);

    $product = Product::factory()->create(['resource_type_id' => $resourceType->id]);
    $partnerProduct = createPartnerProductForClinic($product, $clinic, [
        'purchase_price_misc' => 10.00,
        'purchase_price'      => 10.00,
    ]);

    $orderItem = OrderItem::factory()->create(['product_id' => $product->id]);

    ResourceOrderItem::create([
        'resource_id'  => $resource->id,
        'orderitem_id' => $orderItem->id,
        'from'         => Carbon::tomorrow()->setTime(10, 0),
        'to'           => Carbon::tomorrow()->setTime(11, 0),
    ]);

    $orderItem->refresh();

    expect($orderItem->partner_product_id)->toBe($partnerProduct->id)
        ->and((float) $orderItem->purchasePrice->purchase_price)->toBe(10.0);
});

test('planning does not overwrite manually entered purchase price', function () {
    $clinic = Clinic::factory()->create();
    $dept = ClinicDepartment::factory()->create(['clinic_id' => $clinic->id]);
    $resourceType = ResourceType::firstOrCreate(['name' => 'MRI scanner'], ['description' => null]);
    $resource = Resource::factory()->create([
        'clinic_department_id' => $dept->id,
        'resource_type_id'     => $resourceType->id,
    ]);

    $product = Product::factory()->create(['resource_type_id' => $resourceType->id]);
    $partnerProduct = createPartnerProductForClinic($product, $clinic, [
        'purchase_price_misc' => 999.00,
        'purchase_price'      => 999.00,
    ]);

    $orderItem = OrderItem::factory()->create(['product_id' => $product->id]);
    $orderItem->purchasePrice()->create([
        'type'                => PurchasePriceType::MAIN,
        'purchase_price_misc' => 42.00,
        'purchase_price'      => 42.00,
    ]);

    ResourceOrderItem::create([
        'resource_id'  => $resource->id,
        'orderitem_id' => $orderItem->id,
        'from'         => Carbon::tomorrow()->setTime(10, 0),
        'to'           => Carbon::tomorrow()->setTime(11, 0),
    ]);

    $orderItem->refresh();

    expect($orderItem->partner_product_id)->toBe($partnerProduct->id)
        ->and((float) $orderItem->purchasePrice->purchase_price)->toBe(42.0);
});

test('planning copies price after user cleared purchase price row via update', function () {
    $clinic = Clinic::factory()->create();
    $dept = ClinicDepartment::factory()->create(['clinic_id' => $clinic->id]);
    $resourceType = ResourceType::firstOrCreate(['name' => 'MRI scanner'], ['description' => null]);
    $resource = Resource::factory()->create([
        'clinic_department_id' => $dept->id,
        'resource_type_id'     => $resourceType->id,
    ]);

    $product = Product::factory()->create(['resource_type_id' => $resourceType->id]);
    createPartnerProductForClinic($product, $clinic, [
        'purchase_price_misc' => 75.00,
        'purchase_price'      => 75.00,
    ]);

    $orderItem = OrderItem::factory()->create(['product_id' => $product->id]);
    expect($orderItem->purchasePrice()->exists())->toBeFalse();

    ResourceOrderItem::create([
        'resource_id'  => $resource->id,
        'orderitem_id' => $orderItem->id,
        'from'         => Carbon::tomorrow()->setTime(10, 0),
        'to'           => Carbon::tomorrow()->setTime(11, 0),
    ]);

    expect((float) $orderItem->fresh()->purchasePrice->purchase_price)->toBe(75.0);
});

test('removing planning clears partner_product_id', function () {
    $clinic = Clinic::factory()->create();
    $dept = ClinicDepartment::factory()->create(['clinic_id' => $clinic->id]);
    $resourceType = ResourceType::firstOrCreate(['name' => 'MRI scanner'], ['description' => null]);
    $resource = Resource::factory()->create([
        'clinic_department_id' => $dept->id,
        'resource_type_id'     => $resourceType->id,
    ]);

    $product = Product::factory()->create(['resource_type_id' => $resourceType->id]);
    createPartnerProductForClinic($product, $clinic, [
        'purchase_price_misc' => 50.00,
        'purchase_price'      => 50.00,
    ]);

    $orderItem = OrderItem::factory()->create(['product_id' => $product->id]);

    $booking = ResourceOrderItem::create([
        'resource_id'  => $resource->id,
        'orderitem_id' => $orderItem->id,
        'from'         => Carbon::tomorrow()->setTime(10, 0),
        'to'           => Carbon::tomorrow()->setTime(11, 0),
    ]);

    expect($orderItem->fresh()->partner_product_id)->not->toBeNull();

    $booking->delete();

    $orderItem->refresh();
    expect($orderItem->partner_product_id)->toBeNull()
        ->and((float) $orderItem->purchasePrice->purchase_price)->toBe(50.0);
});
