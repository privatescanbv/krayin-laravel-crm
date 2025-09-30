<?php

namespace Tests\Feature;

use App\Models\Clinic;
use Webkul\Installer\Http\Middleware\CanInstall;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);

    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('clinics index returns datagrid json', function () {
    $c1 = Clinic::factory()->create();
    $c2 = Clinic::factory()->create();

    $response = $this->getJson(route('admin.settings.clinics.index'));
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($c1->id, $c2->id);
});

test('can create clinic', function () {
    $payload = [
        'name'  => 'Test Clinic',
        'email' => 'info@testclinic.tld',
        'phone' => '+31101234567',
    ];

    $response = $this->postJson(route('admin.settings.clinics.store'), $payload);
    $response->assertOk();

    $this->assertDatabaseHas('clinics', [
        'name' => 'Test Clinic',
    ]);
    
    // Verify emails and phones are stored correctly
    $clinic = Clinic::where('name', 'Test Clinic')->first();
    expect($clinic->emails)->toBeArray();
    expect($clinic->emails[0]['value'])->toBe('info@testclinic.tld');
    expect($clinic->phones)->toBeArray();
    expect($clinic->phones[0]['value'])->toBe('+31101234567');
});

test('can update clinic', function () {
    $clinic = Clinic::factory()->create();

    $payload = [
        'name'    => 'Updated Clinic',
        'emails'  => [['value' => 'contact@updated.tld', 'label' => 'eigen', 'is_default' => true]],
        'phones'  => [['value' => '+31102223333', 'label' => 'eigen', 'is_default' => true]],
        '_method' => 'put',
    ];

    $response = $this->postJson(route('admin.settings.clinics.update', ['id' => $clinic->id]), $payload);
    $response->assertOk()->assertJsonPath('data.name', 'Updated Clinic');

    $this->assertDatabaseHas('clinics', [
        'id'   => $clinic->id,
        'name' => 'Updated Clinic',
    ]);
    
    // Verify emails and phones are updated correctly
    $clinic->refresh();
    expect($clinic->emails)->toBeArray();
    expect($clinic->emails[0]['value'])->toBe('contact@updated.tld');
    expect($clinic->phones)->toBeArray();
    expect($clinic->phones[0]['value'])->toBe('+31102223333');
});

test('can update clinic with empty email/phone values filtered out', function () {
    $clinic = Clinic::factory()->create();

    $payload = [
        'name'    => 'Clinic With Filtered Contacts',
        'emails'  => [
            ['value' => 'valid@email.com', 'label' => 'werk', 'is_default' => true],
            ['value' => '', 'label' => 'eigen', 'is_default' => false], // Should be filtered out
        ],
        'phones'  => [
            ['value' => '', 'label' => 'eigen', 'is_default' => true], // Should be filtered out
            ['value' => '+31612345678', 'label' => 'mobiel', 'is_default' => false],
        ],
        '_method' => 'put',
    ];

    $response = $this->postJson(route('admin.settings.clinics.update', ['id' => $clinic->id]), $payload);
    $response->assertOk();

    $clinic->refresh();
    
    // Only valid email should remain
    expect($clinic->emails)->toBeArray();
    expect($clinic->emails)->toHaveCount(1);
    expect($clinic->emails[0]['value'])->toBe('valid@email.com');
    
    // Only valid phone should remain
    expect($clinic->phones)->toBeArray();
    expect($clinic->phones)->toHaveCount(1);
    expect($clinic->phones[0]['value'])->toBe('+31612345678');
});

test('can delete clinic', function () {
    $clinic = Clinic::factory()->create();

    $response = $this->deleteJson(route('admin.settings.clinics.delete', ['id' => $clinic->id]));
    $response->assertOk();

    $this->assertDatabaseMissing('clinics', [
        'id' => $clinic->id,
    ]);
});
