<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\PartnerProduct;
use App\Models\ResourceType;
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

    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;
    $clinicId = Clinic::query()->value('id') ?? Clinic::factory()->create()->id;
    $payload = [
        'name'               => 'MRI Scan',
        'currency'           => 'EUR',
        'sales_price'        => 199.99,
        'active'             => 1,
        'description'        => 'Great partner product',
        'discount_info'      => 'Intro discount 10%',
        'resource_type_id'   => $resourceTypeId,
        'clinics'            => [$clinicId],
        'clinic_description' => 'Omschrijving kliniek',
        'duration'           => 60,
    ];

    $response = $this->postJson(route('admin.settings.partner_products.store'), $payload);
    $response->assertOk();

    $this->assertDatabaseHas('partner_products', [
        'name' => 'MRI Scan',
    ]);
});

test('can update partner product', function () {
    $pp = PartnerProduct::factory()->create();

    $resourceTypeId = ResourceType::query()->value('id') ?? ResourceType::factory()->create()->id;
    $clinicId = Clinic::query()->value('id') ?? Clinic::factory()->create()->id;

    $payload = [
        'name'               => 'CT Scan',
        'currency'           => 'EUR',
        'sales_price'        => 299.95,
        'active'             => 0,
        'description'        => 'Updated description',
        'discount_info'      => null,
        'resource_type_id'   => $resourceTypeId,
        'clinic_description' => 'Nieuwe omschrijving kliniek',
        'duration'           => 45,
        'clinics'            => [$clinicId],
        '_method'            => 'put',
    ];

    $response = $this->postJson(route('admin.settings.partner_products.update', ['id' => $pp->id]), $payload);
    $response->assertOk()->assertJsonPath('data.name', 'CT Scan');

    $this->assertDatabaseHas('partner_products', [
        'id'   => $pp->id,
        'name' => 'CT Scan',
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
