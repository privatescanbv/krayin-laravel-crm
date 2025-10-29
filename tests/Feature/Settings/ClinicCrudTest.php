<?php

namespace Tests\Feature;

use App\Enums\ContactLabel;
use App\Models\Clinic;
use App\Repositories\ClinicRepository;
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

    $response = $this->getJson(route('admin.clinics.index'));
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($c1->id, $c2->id);
});

test('can create clinic', function () {
    $payload = [
        'name'                    => 'Test Clinic',
        'email'                   => 'info@testclinic.tld',
        'phone'                   => '+31101234567',
        'website_url'             => 'https://www.testclinic.nl',
        'order_confirmation_note' => 'Meld je bij de receptie',
    ];

    $response = $this->postJson(route('admin.clinics.store'), $payload);
    $response->assertOk();

    $this->assertDatabaseHas('clinics', [
        'name'                    => 'Test Clinic',
        'website_url'             => 'https://www.testclinic.nl',
        'order_confirmation_note' => 'Meld je bij de receptie',
    ]);

    // Verify emails and phones are stored correctly
    $clinic = Clinic::where('name', 'Test Clinic')->first();
    expect($clinic->emails)->toBeArray()
        ->and($clinic->emails[0]['value'])->toBe('info@testclinic.tld')
        ->and($clinic->phones)->toBeArray()
        ->and($clinic->phones[0]['value'])->toBe('+31101234567');
});

test('can update clinic', function () {
    $clinic = Clinic::factory()->create();

    $payload = [
        'name'                    => 'Updated Clinic',
        'website_url'             => 'https://www.updated-clinic.com',
        'order_confirmation_note' => 'Nieuwe opmerking voor patiënten',
        'emails'                  => [['value' => 'contact@updated.tld', 'label' => 'eigen', 'is_default' => true]],
        'phones'                  => [['value' => '+31102223333', 'label' => 'eigen', 'is_default' => true]],
        '_method'                 => 'put',
    ];

    $response = $this->postJson(route('admin.clinics.update', ['id' => $clinic->id]), $payload);
    $response->assertOk()->assertJsonPath('data.name', 'Updated Clinic');

    $this->assertDatabaseHas('clinics', [
        'id'                      => $clinic->id,
        'name'                    => 'Updated Clinic',
        'website_url'             => 'https://www.updated-clinic.com',
        'order_confirmation_note' => 'Nieuwe opmerking voor patiënten',
    ]);

    // Verify emails and phones are updated correctly
    $clinic->refresh();
    expect($clinic->emails)->toBeArray()
        ->and($clinic->emails[0]['value'])->toBe('contact@updated.tld')
        ->and($clinic->phones)->toBeArray()
        ->and($clinic->phones[0]['value'])->toBe('+31102223333');
});

test('can update clinic with empty email/phone values filtered out', function () {
    $clinic = Clinic::factory()->create();

    $payload = [
        'name'    => 'Clinic With Filtered Contacts',
        'emails'  => [
            ['value' => 'valid@email.com', 'label' => ContactLabel::Relatie->value, 'is_default' => true],
            ['value' => '', 'label' => ContactLabel::Eigen->value, 'is_default' => false], // Should be filtered out
        ],
        'phones'  => [
            ['value' => '', 'label' => ContactLabel::Eigen->value, 'is_default' => true], // Should be filtered out
            ['value' => '+31612345678', 'label' => ContactLabel::Relatie->value, 'is_default' => false],
        ],
        '_method' => 'put',
    ];

    $response = $this->postJson(route('admin.clinics.update', ['id' => $clinic->id]), $payload);
    $response->assertOk();

    $clinic->refresh();

    // Only valid email should remain
    expect($clinic->emails)->toBeArray()
        ->and($clinic->emails)->toHaveCount(1)
        ->and($clinic->emails[0]['value'])->toBe('valid@email.com')
        ->and($clinic->phones)->toBeArray()
        ->and($clinic->phones)->toHaveCount(1)
        ->and($clinic->phones[0]['value'])->toBe('+31612345678');

    // Only valid phone should remain
});

test('validates website url is a valid url', function () {
    $payload = [
        'name'        => 'Test Clinic URL Validation',
        'email'       => 'info@testclinic.tld',
        'website_url' => 'not-a-valid-url',
    ];

    $response = $this->postJson(route('admin.clinics.store'), $payload);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['website_url']);
});

test('accepts valid http and https urls', function () {
    $payload = [
        'name'        => 'Test Clinic Valid URL',
        'email'       => 'info@testclinic.tld',
        'website_url' => 'https://www.testclinic.nl',
    ];

    $response = $this->postJson(route('admin.clinics.store'), $payload);
    $response->assertOk();

    $this->assertDatabaseHas('clinics', [
        'name'        => 'Test Clinic Valid URL',
        'website_url' => 'https://www.testclinic.nl',
    ]);
});

test('allActive returns only active clinics (is_active = 1)', function () {
    // Arrange: create active and inactive clinics
    $active = Clinic::factory()->create(['is_active' => 1, 'name' => 'Active Clinic']);
    $inactive = Clinic::factory()->create(['is_active' => 0, 'name' => 'Inactive Clinic']);

    // Act: fetch via repository
    $resultIds = app(ClinicRepository::class)
        ->allActive(['id'])
        ->pluck('id')
        ->all();

    // Assert: only active clinic id is present
    expect($resultIds)->toContain($active->id)
        ->and($resultIds)->not->toContain($inactive->id);
});

test('can delete clinic', function () {
    $clinic = Clinic::factory()->create();

    $response = $this->deleteJson(route('admin.clinics.delete', ['id' => $clinic->id]));
    $response->assertOk();

    $this->assertDatabaseMissing('clinics', [
        'id' => $clinic->id,
    ]);
});
