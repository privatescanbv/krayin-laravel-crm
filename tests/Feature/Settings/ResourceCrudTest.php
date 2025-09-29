<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Resource;
use App\Models\ResourceType;
use Webkul\Installer\Http\Middleware\CanInstall;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);

    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('resources index returns datagrid json', function () {
    $r1 = Resource::factory()->create();
    $r2 = Resource::factory()->create();

    $response = $this->getJson(route('admin.settings.resources.index'));
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($r1->id, $r2->id);
});

test('can create resource', function () {
    $resourceType = ResourceType::factory()->create();
    $clinic = Clinic::factory()->create();

    $payload = [
        'name'             => 'Test Resource',
        'resource_type_id' => $resourceType->id,
        'clinic_id'        => $clinic->id,
    ];

    $response = $this->postJson(route('admin.settings.resources.store'), $payload);
    $response->assertOk()->assertJsonPath('data.name', 'Test Resource');

    $this->assertDatabaseHas('resources', [
        'name' => 'Test Resource',
    ]);
});

test('can update resource', function () {
    $resource = Resource::factory()->create();

    $payload = [
        'name'             => 'Updated Resource',
        'resource_type_id' => $resource->resource_type_id,
        'clinic_id'        => $resource->clinic_id,
        '_method'          => 'put',
    ];

    $response = $this->postJson(route('admin.settings.resources.update', ['id' => $resource->id]), $payload);
    $response->assertOk()->assertJsonPath('data.name', 'Updated Resource');

    $this->assertDatabaseHas('resources', [
        'id'   => $resource->id,
        'name' => 'Updated Resource',
    ]);
});

test('can delete resource', function () {
    $resource = Resource::factory()->create();

    $response = $this->deleteJson(route('admin.settings.resources.delete', ['id' => $resource->id]));
    $response->assertOk();

    $this->assertDatabaseMissing('resources', [
        'id' => $resource->id,
    ]);
});
