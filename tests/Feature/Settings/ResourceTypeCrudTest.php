<?php

namespace Tests\Feature;

use App\Models\ResourceType;
use Webkul\Installer\Http\Middleware\CanInstall;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);
});

test('resource types index returns datagrid json', function () {
    $user = makeUser();
    $this->actingAs($user, 'user');

    $t1 = ResourceType::factory()->create();
    $t2 = ResourceType::factory()->create();

    $response = $this->getJson(route('admin.settings.resource_types.index'));
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($t1->id, $t2->id);
});

test('can create resource type', function () {
    $user = makeUser();
    $this->actingAs($user, 'user');

    $payload = [
        'name'        => 'MRI Scanner',
        'description' => 'Magnetic resonance imaging',
    ];

    $response = $this->post(route('admin.settings.resource_types.store'), $payload);
    $response->assertRedirect(route('admin.settings.resource_types.index'));

    $this->assertDatabaseHas('resource_types', [
        'name' => 'MRI Scanner',
    ]);
});

test('can update resource type', function () {
    $user = makeUser();
    $this->actingAs($user, 'user');

    $entity = ResourceType::factory()->create();

    $payload = [
        'name'        => 'CT Scanner',
        'description' => 'Computed tomography',
        '_method'     => 'put',
    ];

    $response = $this->post(route('admin.settings.resource_types.update', ['id' => $entity->id]), $payload);
    $response->assertRedirect(route('admin.settings.resource_types.index'));

    $this->assertDatabaseHas('resource_types', [
        'id'   => $entity->id,
        'name' => 'CT Scanner',
    ]);
});

test('can delete resource type', function () {
    $user = makeUser();
    $this->actingAs($user, 'user');

    $entity = ResourceType::factory()->create();

    $response = $this->deleteJson(route('admin.settings.resource_types.delete', ['id' => $entity->id]));
    $response->assertOk();

    $this->assertDatabaseMissing('resource_types', [
        'id' => $entity->id,
    ]);
});
