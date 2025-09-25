<?php

namespace Tests\Feature;

use App\Models\Resource;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\User\Models\User;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);
});

function makeUser(array $attrs = []): User
{
    return User::factory()->create(array_merge(['status' => 1], $attrs));
}

function getDatagridIds($response): array
{
    $payload = $response->json();
    $records = $payload['records'] ?? [];

    return collect($records)->pluck('id')->all();
}

test('resources index returns datagrid json', function () {
    $user = makeUser();
    $this->actingAs($user, 'user');

    $r1 = Resource::factory()->create();
    $r2 = Resource::factory()->create();

    $response = $this->getJson(route('admin.settings.resources.index'));
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($r1->id, $r2->id);
});

test('can create resource', function () {
    $user = makeUser();
    $this->actingAs($user, 'user');

    $payload = [
        'type' => 'staff',
        'name' => 'Test Resource',
    ];

    $response = $this->postJson(route('admin.settings.resources.store'), $payload);
    $response->assertOk()->assertJsonPath('data.name', 'Test Resource');

    $this->assertDatabaseHas('resources', [
        'name' => 'Test Resource',
    ]);
});

test('can update resource', function () {
    $user = makeUser();
    $this->actingAs($user, 'user');

    $resource = Resource::factory()->create();

    $payload = [
        'type'    => 'room',
        'name'    => 'Updated Resource',
        '_method' => 'put',
    ];

    $response = $this->postJson(route('admin.settings.resources.update', ['id' => $resource->id]), $payload);
    $response->assertOk()->assertJsonPath('data.name', 'Updated Resource');

    $this->assertDatabaseHas('resources', [
        'id'   => $resource->id,
        'name' => 'Updated Resource',
    ]);
});

test('can delete resource', function () {
    $user = makeUser();
    $this->actingAs($user, 'user');

    $resource = Resource::factory()->create();

    $response = $this->deleteJson(route('admin.settings.resources.delete', ['id' => $resource->id]));
    $response->assertOk();

    $this->assertDatabaseMissing('resources', [
        'id' => $resource->id,
    ]);
});

