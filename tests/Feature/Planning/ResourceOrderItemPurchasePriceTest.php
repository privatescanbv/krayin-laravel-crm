<?php

namespace Tests\Feature\Planning;

use App\Enums\PurchasePriceType;
use App\Models\Clinic;
use App\Models\ClinicDepartment;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use App\Models\ResourceType;
use Carbon\Carbon;
use Webkul\Product\Models\Product;

test('planning een order item kopieert de inkoopprijs van het clinic-specifieke partner product', function () {
    $clinic = Clinic::factory()->create();
    $dept = ClinicDepartment::factory()->create(['clinic_id' => $clinic->id]);
    $resourceType = ResourceType::firstOrCreate(['name' => 'MRI scanner'], ['description' => null]);
    $resource = Resource::factory()->create([
        'clinic_department_id' => $dept->id,
        'resource_type_id'     => $resourceType->id,
    ]);

    $product = Product::factory()->create(['resource_type_id' => $resourceType->id]);

    $partnerProduct = PartnerProduct::factory()->create(['product_id' => $product->id]);
    $partnerProduct->clinics()->sync([$clinic->id]);
    $partnerProduct->purchasePrice()->update([
        'purchase_price_misc'       => 10.00,
        'purchase_price_doctor'     => 20.00,
        'purchase_price_cardiology' => 30.00,
        'purchase_price_clinic'     => 40.00,
        'purchase_price_radiology'  => 50.00,
        'purchase_price'            => 150.00,
    ]);

    $orderItem = OrderItem::factory()->create(['product_id' => $product->id]);

    ResourceOrderItem::create([
        'resource_id'  => $resource->id,
        'orderitem_id' => $orderItem->id,
        'from'         => Carbon::tomorrow()->setTime(10, 0),
        'to'           => Carbon::tomorrow()->setTime(11, 0),
    ]);

    $price = $orderItem->purchasePrice()->first();
    expect($price)->not->toBeNull()
        ->and((float) $price->purchase_price_misc)->toBe(10.00)
        ->and((float) $price->purchase_price_doctor)->toBe(20.00)
        ->and((float) $price->purchase_price_cardiology)->toBe(30.00)
        ->and((float) $price->purchase_price_clinic)->toBe(40.00)
        ->and((float) $price->purchase_price_radiology)->toBe(50.00)
        ->and((float) $price->purchase_price)->toBe(150.00)
        ->and($price->type)->toBe(PurchasePriceType::MAIN);
});

test('planning zonder matching partner product maakt geen purchase price aan en geeft geen error', function () {
    $clinic = Clinic::factory()->create();
    $otherClinic = Clinic::factory()->create();
    $dept = ClinicDepartment::factory()->create(['clinic_id' => $clinic->id]);
    $resourceType = ResourceType::firstOrCreate(['name' => 'MRI scanner'], ['description' => null]);
    $resource = Resource::factory()->create([
        'clinic_department_id' => $dept->id,
        'resource_type_id'     => $resourceType->id,
    ]);

    $product = Product::factory()->create(['resource_type_id' => $resourceType->id]);

    // PartnerProduct is gekoppeld aan een ANDERE kliniek
    $partnerProduct = PartnerProduct::factory()->create(['product_id' => $product->id]);
    $partnerProduct->clinics()->sync([$otherClinic->id]);

    $orderItem = OrderItem::factory()->create(['product_id' => $product->id]);

    ResourceOrderItem::create([
        'resource_id'  => $resource->id,
        'orderitem_id' => $orderItem->id,
        'from'         => Carbon::tomorrow()->setTime(10, 0),
        'to'           => Carbon::tomorrow()->setTime(11, 0),
    ]);

    expect($orderItem->purchasePrice()->exists())->toBeFalse();
});

test('planning overschrijft een bestaande purchase price op het order item met de kliniek-specifieke prijs', function () {
    $clinic = Clinic::factory()->create();
    $dept = ClinicDepartment::factory()->create(['clinic_id' => $clinic->id]);
    $resourceType = ResourceType::firstOrCreate(['name' => 'MRI scanner'], ['description' => null]);
    $resource = Resource::factory()->create([
        'clinic_department_id' => $dept->id,
        'resource_type_id'     => $resourceType->id,
    ]);

    $product = Product::factory()->create(['resource_type_id' => $resourceType->id]);

    $partnerProduct = PartnerProduct::factory()->create(['product_id' => $product->id]);
    $partnerProduct->clinics()->sync([$clinic->id]);
    $partnerProduct->purchasePrice->update([
        'purchase_price_misc'       => 999.00,
        'purchase_price_doctor'     => 0,
        'purchase_price_cardiology' => 0,
        'purchase_price_clinic'     => 0,
        'purchase_price_radiology'  => 0,
        'purchase_price'            => 999.00,
    ]);

    $orderItem = OrderItem::factory()->create(['product_id' => $product->id]);

    // Bestaande (handmatige) inkoopprijs — wordt overschreven
    $orderItem->purchasePrice()->create([
        'type'                      => PurchasePriceType::MAIN,
        'purchase_price_misc'       => 42.00,
        'purchase_price_doctor'     => 0,
        'purchase_price_cardiology' => 0,
        'purchase_price_clinic'     => 0,
        'purchase_price_radiology'  => 0,
        'purchase_price'            => 42.00,
    ]);

    ResourceOrderItem::create([
        'resource_id'  => $resource->id,
        'orderitem_id' => $orderItem->id,
        'from'         => Carbon::tomorrow()->setTime(10, 0),
        'to'           => Carbon::tomorrow()->setTime(11, 0),
    ]);

    expect((float) $orderItem->purchasePrice()->value('purchase_price'))->toBe(999.00);
    expect($orderItem->purchasePrice()->count())->toBe(1);
});

test('planning zonder clinicDepartment op resource maakt geen purchase price aan en geeft geen error', function () {
    $resourceType = ResourceType::firstOrCreate(['name' => 'MRI scanner'], ['description' => null]);

    // Resource zonder clinicDepartment (clinic_department_id = null)
    $resource = Resource::factory()->create([
        'resource_type_id'     => $resourceType->id,
        'clinic_department_id' => null,
    ]);

    $product = Product::factory()->create(['resource_type_id' => $resourceType->id]);
    $orderItem = OrderItem::factory()->create(['product_id' => $product->id]);

    ResourceOrderItem::create([
        'resource_id'  => $resource->id,
        'orderitem_id' => $orderItem->id,
        'from'         => Carbon::tomorrow()->setTime(10, 0),
        'to'           => Carbon::tomorrow()->setTime(11, 0),
    ]);

    expect($orderItem->purchasePrice()->exists())->toBeFalse();
});
