<?php

namespace Tests\Feature;

use App\Models\Clinic;
use Webkul\Installer\Http\Middleware\CanInstall;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);
});

test('clinics index returns datagrid json', function () {
    $user = makeUser();
    $this->actingAs($user, 'user');

    $c1 = Clinic::factory()->create();
    $c2 = Clinic::factory()->create();

    $response = $this->getJson(route('admin.settings.clinics.index'));
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($c1->id, $c2->id);
});

test('can create clinic', function () {
    $user = makeUser();
    $this->actingAs($user, 'user');

    $payload = [
        'name'   => 'Test Clinic',
        'emails' => ['info@testclinic.tld'],
        'phones' => ['+31 10 123 4567'],
    ];

    $response = $this->postJson(route('admin.settings.clinics.store'), $payload);
    $response->assertOk();

    $this->assertDatabaseHas('clinics', [
        'name' => 'Test Clinic',
    ]);
});

test('can update clinic', function () {
    $user = makeUser();
    $this->actingAs($user, 'user');

    $clinic = Clinic::factory()->create();

    $payload = [
        'name'    => 'Updated Clinic',
        'emails'  => ['contact@updated.tld'],
        'phones'  => ['+31 10 222 3333'],
        '_method' => 'put',
    ];

    $response = $this->postJson(route('admin.settings.clinics.update', ['id' => $clinic->id]), $payload);
    $response->assertOk()->assertJsonPath('data.name', 'Updated Clinic');

    $this->assertDatabaseHas('clinics', [
        'id'   => $clinic->id,
        'name' => 'Updated Clinic',
    ]);
});

test('can delete clinic', function () {
    $user = makeUser();
    $this->actingAs($user, 'user');

    $clinic = Clinic::factory()->create();

    $response = $this->deleteJson(route('admin.settings.clinics.delete', ['id' => $clinic->id]));
    $response->assertOk();

    $this->assertDatabaseMissing('clinics', [
        'id' => $clinic->id,
    ]);
});
