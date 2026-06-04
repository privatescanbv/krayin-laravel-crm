<?php

namespace Tests\Feature\Planning;

use App\Models\Clinic;
use App\Models\ClinicDepartment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\Resource;
use App\Models\Shift;
use Carbon\Carbon;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Product\Models\Product;

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);
    $this->actingAs(makeUser(), 'user');
});

function plannerResourceWithShift(): Resource
{
    $resource = Resource::factory()->create();
    Shift::factory()->create([
        'resource_id'  => $resource->id,
        'period_start' => Carbon::now()->startOfWeek(),
        'period_end'   => Carbon::now()->endOfWeek()->addDays(7),
    ]);

    return $resource->fresh(['clinicDepartment']);
}

test('availability includes clinic_bookable_product_ids for resource clinic', function () {
    $clinic = Clinic::factory()->create();
    $product = Product::factory()->create();
    $department = ClinicDepartment::factory()->create(['clinic_id' => $clinic->id]);
    $resource = plannerResourceWithShift();
    $resource->update(['clinic_department_id' => $department->id]);

    $partnerProduct = PartnerProduct::factory()->create([
        'product_id' => $product->id,
        'active'     => true,
    ]);
    $partnerProduct->clinics()->sync([$clinic->id]);

    $response = test()->getJson(route('admin.planning.monitor.availability'));
    $response->assertOk();

    $found = collect($response->json('resources'))->firstWhere('id', $resource->id);

    expect($found)->not->toBeNull()
        ->and($found['clinic_bookable_product_ids'])->toContain($product->id);
});

test('order availability filters resources by order item product and clinic partner product', function () {
    $clinicWithProduct = Clinic::factory()->create();
    $clinicWithoutProduct = Clinic::factory()->create();
    $product = Product::factory()->create();

    $departmentMatch = ClinicDepartment::factory()->create(['clinic_id' => $clinicWithProduct->id]);
    $departmentOther = ClinicDepartment::factory()->create(['clinic_id' => $clinicWithoutProduct->id]);

    $matchingResource = plannerResourceWithShift();
    $matchingResource->update(['clinic_department_id' => $departmentMatch->id]);

    $otherResource = plannerResourceWithShift();
    $otherResource->update(['clinic_department_id' => $departmentOther->id]);

    $partnerProduct = PartnerProduct::factory()->create([
        'product_id' => $product->id,
        'active'     => true,
    ]);
    $partnerProduct->clinics()->sync([$clinicWithProduct->id]);

    $order = Order::factory()->create();
    $orderItem = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
    ]);

    $response = test()->getJson(route('admin.planning.monitor.order.availability', [
        'orderId'        => $order->id,
        'order_item_ids' => (string) $orderItem->id,
    ]));

    $response->assertOk();

    $resourceIds = collect($response->json('resources'))->pluck('id')->all();

    expect($resourceIds)->toContain($matchingResource->id)
        ->and($resourceIds)->not->toContain($otherResource->id);
});
