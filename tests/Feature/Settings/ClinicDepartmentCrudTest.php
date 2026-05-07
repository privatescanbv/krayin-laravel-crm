<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\ClinicDepartment;
use Webkul\Installer\Http\Middleware\CanInstall;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);

    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('clinic departments index returns datagrid json', function () {
    $d1 = ClinicDepartment::factory()->create();
    $d2 = ClinicDepartment::factory()->create();

    $response = $this->getJson(route('admin.clinic_departments.index'));
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($d1->id, $d2->id);
});

test('can create clinic department', function () {
    $clinic = Clinic::factory()->create();

    $payload = [
        'clinic_id'               => $clinic->id,
        'name'                    => 'Test Department',
        'email'                   => 'dept@testclinic.tld',
        'order_confirmation_note' => 'Breng uw identiteitsbewijs mee.',
    ];

    $response = $this->postJson(route('admin.clinic_departments.store'), $payload);
    $response->assertOk();

    $this->assertDatabaseHas('clinic_departments', [
        'name'                    => 'Test Department',
        'order_confirmation_note' => 'Breng uw identiteitsbewijs mee.',
    ]);
});

test('can update clinic department', function () {
    $dept = ClinicDepartment::factory()->create();

    $payload = [
        'clinic_id'               => $dept->clinic_id,
        'name'                    => 'Updated Department',
        'email'                   => 'updated@testclinic.tld',
        'order_confirmation_note' => 'Vergeet uw verwijsbrief niet.',
    ];

    $response = $this->putJson(route('admin.clinic_departments.update', ['id' => $dept->id]), $payload);
    $response->assertOk();

    $this->assertDatabaseHas('clinic_departments', [
        'id'                      => $dept->id,
        'name'                    => 'Updated Department',
        'order_confirmation_note' => 'Vergeet uw verwijsbrief niet.',
    ]);

    $dept->refresh();
    expect($dept->order_confirmation_note)->toBe('Vergeet uw verwijsbrief niet.');
});

test('can update clinic department with null order_confirmation_note', function () {
    $dept = ClinicDepartment::factory()->create(['order_confirmation_note' => 'Old note']);

    $payload = [
        'clinic_id'               => $dept->clinic_id,
        'name'                    => $dept->name,
        'email'                   => $dept->email,
        'order_confirmation_note' => null,
    ];

    $response = $this->putJson(route('admin.clinic_departments.update', ['id' => $dept->id]), $payload);
    $response->assertOk();

    $dept->refresh();
    expect($dept->order_confirmation_note)->toBeNull();
});

test('can delete clinic department', function () {
    $dept = ClinicDepartment::factory()->create();

    $response = $this->deleteJson(route('admin.clinic_departments.delete', ['id' => $dept->id]));
    $response->assertOk();

    $this->assertDatabaseMissing('clinic_departments', [
        'id' => $dept->id,
    ]);
});
