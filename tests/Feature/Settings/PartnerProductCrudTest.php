<?php

namespace Tests\Feature;

use App\Models\PartnerProduct;
use Webkul\Installer\Http\Middleware\CanInstall;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);

    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('partner products index returns datagrid json', function () {
    $p1 = PartnerProduct::factory()->create();
    $p2 = PartnerProduct::factory()->create();

    $response = $this->getJson(route('admin.settings.partner_products.index'));
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($p1->id, $p2->id);
});

test('can create partner product', function () {
    $payload = [
        'partner_name' => 'Acme Partner',
        'description'  => 'Great partner product',
    ];

    $response = $this->postJson(route('admin.settings.partner_products.store'), $payload);
    $response->assertOk();

    $this->assertDatabaseHas('partner_products', [
        'partner_name' => 'Acme Partner',
    ]);
});

test('can update partner product', function () {
    $pp = PartnerProduct::factory()->create();

    $payload = [
        'partner_name' => 'Updated Partner Name',
        'description'  => 'Updated description',
        '_method'      => 'put',
    ];

    $response = $this->postJson(route('admin.settings.partner_products.update', ['id' => $pp->id]), $payload);
    $response->assertOk()->assertJsonPath('data.partner_name', 'Updated Partner Name');

    $this->assertDatabaseHas('partner_products', [
        'id'           => $pp->id,
        'partner_name' => 'Updated Partner Name',
    ]);
});

test('can delete partner product', function () {
    $pp = PartnerProduct::factory()->create();

    $response = $this->deleteJson(route('admin.settings.partner_products.delete', ['id' => $pp->id]));
    $response->assertOk();

    $this->assertDatabaseMissing('partner_products', [
        'id' => $pp->id,
    ]);
});

