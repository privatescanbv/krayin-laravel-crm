<?php

namespace Tests\Feature;

use App\Models\ProductType;
use Webkul\Installer\Http\Middleware\CanInstall;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);

    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('product types index returns datagrid json', function () {
    $t1 = ProductType::factory()->create();
    $t2 = ProductType::factory()->create();

    $response = $this->getJson(route('admin.settings.product_types.index'));
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($t1->id, $t2->id);
});

test('can create product type', function () {
    $payload = [
        'name'        => 'Total Bodyscan',
        'description' => 'Test type',
    ];

    $response = $this->postJson(route('admin.settings.product_types.store'), $payload);
    $response->assertOk();

    $this->assertDatabaseHas('product_types', [
        'name' => 'Total Bodyscan',
    ]);
});

test('can update product type', function () {
    $type = ProductType::factory()->create();

    $payload = [
        'name'    => 'Updated Type',
        'description'  => 'Updated',
        '_method' => 'put',
    ];

    $response = $this->postJson(route('admin.settings.product_types.update', ['id' => $type->id]), $payload);
    $response->assertOk()->assertJsonPath('data.name', 'Updated Type');

    $this->assertDatabaseHas('product_types', [
        'id'   => $type->id,
        'name' => 'Updated Type',
    ]);
});

test('can delete product type', function () {
    $type = ProductType::factory()->create();

    $response = $this->deleteJson(route('admin.settings.product_types.delete', ['id' => $type->id]));
    $response->assertOk();

    $this->assertDatabaseMissing('product_types', [
        'id' => $type->id,
    ]);
});

