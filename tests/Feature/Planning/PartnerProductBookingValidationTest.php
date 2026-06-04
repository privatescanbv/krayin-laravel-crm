<?php

namespace Tests\Feature\Planning;

use App\Models\Clinic;
use App\Models\ClinicDepartment;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use App\Models\ResourceType;
use Carbon\Carbon;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Product\Models\Product;

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);
    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('order item book returns 422 when no active partner product for resource clinic', function () {
    $date = Carbon::tomorrow()->setTime(10, 0);
    $orderItem = OrderItem::factory()->create();
    $resource = resourceWithShiftCovering($date);

    $response = $this->postJson(
        route('admin.planning.order_item.book', ['orderItemId' => $orderItem->id]),
        [
            'resource_id' => $resource->id,
            'from'        => $date->toIso8601String(),
            'to'          => $date->copy()->addHour()->toIso8601String(),
        ]
    );

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('geen actief partnerproduct');
    $this->assertDatabaseMissing('resource_orderitem', ['orderitem_id' => $orderItem->id]);
});

test('order item book succeeds when active partner product matches resource clinic', function () {
    $date = Carbon::tomorrow()->setTime(10, 0);
    $orderItem = OrderItem::factory()->create();
    $resource = resourceWithShiftCovering($date);
    attachPartnerProductForOrderItemAndResource($orderItem, $resource);

    $response = $this->postJson(
        route('admin.planning.order_item.book', ['orderItemId' => $orderItem->id]),
        [
            'resource_id' => $resource->id,
            'from'        => $date->toIso8601String(),
            'to'          => $date->copy()->addHour()->toIso8601String(),
        ]
    );

    $response->assertStatus(201);
    $this->assertDatabaseHas('resource_orderitem', [
        'orderitem_id' => $orderItem->id,
        'resource_id'  => $resource->id,
    ]);
});

test('monitor book returns 422 when partner product linked to different clinic', function () {
    $resourceType = ResourceType::firstOrCreate(
        ['name' => 'MRI scanner'],
        ['description' => null]
    );

    $product = Product::factory()->create([
        'resource_type_id' => $resourceType->id,
    ]);

    $orderItem = OrderItem::factory()->create([
        'product_id' => $product->id,
    ]);

    $resource = Resource::factory()->create([
        'resource_type_id' => $resourceType->id,
    ]);

    $otherClinic = Clinic::factory()->create();
    $partnerProduct = PartnerProduct::factory()->create([
        'product_id' => $product->id,
        'active'     => true,
    ]);
    $partnerProduct->clinics()->sync([$otherClinic->id]);

    $from = Carbon::tomorrow()->setTime(10, 0);
    $to = $from->copy()->addHour();

    $response = $this->postJson(
        route('admin.planning.monitor.order_item.book', ['orderItemId' => $orderItem->id]),
        [
            'resource_id' => $resource->id,
            'from'        => $from->toIso8601String(),
            'to'          => $to->toIso8601String(),
        ]
    );

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('geen actief partnerproduct');
});

test('monitor update booking returns 422 when new resource clinic has no partner product', function () {
    $resourceType = ResourceType::firstOrCreate(
        ['name' => 'MRI scanner'],
        ['description' => null]
    );

    $product = Product::factory()->create([
        'resource_type_id' => $resourceType->id,
    ]);

    $orderItem = OrderItem::factory()->create([
        'product_id' => $product->id,
    ]);

    $clinicA = Clinic::factory()->create();
    $clinicB = Clinic::factory()->create();

    $resourceA = Resource::factory()->create([
        'resource_type_id'     => $resourceType->id,
        'clinic_department_id' => ClinicDepartment::factory()->create(['clinic_id' => $clinicA->id])->id,
    ]);

    $resourceB = Resource::factory()->create([
        'resource_type_id'     => $resourceType->id,
        'clinic_department_id' => ClinicDepartment::factory()->create(['clinic_id' => $clinicB->id])->id,
    ]);

    $partnerProduct = PartnerProduct::factory()->create([
        'product_id' => $product->id,
        'active'     => true,
    ]);
    $partnerProduct->clinics()->sync([$clinicA->id]);

    $booking = ResourceOrderItem::factory()->create([
        'orderitem_id' => $orderItem->id,
        'resource_id'  => $resourceA->id,
    ]);

    $from = Carbon::tomorrow()->setTime(10, 0);
    $to = $from->copy()->addHour();

    $response = $this->putJson(
        route('admin.planning.monitor.booking.update', ['bookingId' => $booking->id]),
        [
            'resource_id' => $resourceB->id,
            'from'        => $from->toIso8601String(),
            'to'          => $to->toIso8601String(),
        ]
    );

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('geen actief partnerproduct');
    expect($booking->fresh()->resource_id)->toBe($resourceA->id);
});
