<?php

namespace Tests\Feature\Planning;

use App\Models\OrderItem;
use App\Models\ProductType;
use App\Models\Resource;
use App\Models\ResourceType;
use Carbon\Carbon;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Product\Models\Product;

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);
    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('monitor book returns 422 with readable message when resource type mismatches order item', function () {
    $mriType = ResourceType::firstOrCreate(
        ['name' => 'MRI scanner'],
        ['description' => null]
    );
    $ctType = ResourceType::firstOrCreate(
        ['name' => 'CT scanner'],
        ['description' => null]
    );

    $productType = ProductType::firstOrCreate(
        ['name' => 'MRI scan'],
        ['description' => '']
    );

    $product = Product::factory()->create([
        'product_type_id'  => $productType->id,
        'resource_type_id' => $mriType->id,
    ]);

    $orderItem = OrderItem::factory()->create([
        'product_id' => $product->id,
    ]);

    $resource = Resource::factory()->create([
        'resource_type_id' => $ctType->id,
    ]);

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
    $response->assertJsonPath('required_type', 'MRI scanner');
    $response->assertJsonPath('resource_type', 'CT scanner');

    $message = $response->json('message');
    expect($message)->toContain('MRI scanner')
        ->and($message)->toContain('CT scanner')
        ->and($message)->toContain('Dit orderregel vereist');
});
