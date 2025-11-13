<?php

use App\Models\Clinic;
use Tests\Feature\Concerns\ControllerSearchTestHelpers;

uses(ControllerSearchTestHelpers::class);

beforeEach(function () {
    $this->setUpSearchTest();
});

test('clinic search with query parameter filters by name', function () {
    $clinicMatch = Clinic::factory()->create(['name' => 'Amsterdam Clinic']);
    $clinicPartial = Clinic::factory()->create(['name' => 'Amsterdam Medical Center']);
    $clinicNoMatch = Clinic::factory()->create(['name' => 'Rotterdam Hospital']);

    $response = $this->performSearch('admin.clinics.search', ['query' => 'Amsterdam']);

    $this->assertEntityFound($response, $clinicMatch->id);
    $this->assertEntityFound($response, $clinicPartial->id);
    $this->assertEntityNotFound($response, $clinicNoMatch->id);
});

test('clinic search returns empty array when no matches', function () {
    Clinic::factory()->create(['name' => 'Amsterdam Clinic']);
    Clinic::factory()->create(['name' => 'Rotterdam Hospital']);

    $response = $this->performSearch('admin.clinics.search', ['query' => 'NonExistent']);

    $this->assertSearchEmpty($response);
});

test('clinic search returns all when query is empty', function () {
    $clinic1 = Clinic::factory()->create(['name' => 'Amsterdam Clinic']);
    $clinic2 = Clinic::factory()->create(['name' => 'Rotterdam Hospital']);

    $response = $this->performSearch('admin.clinics.search');

    $this->assertSearchReturnsAll($response, [$clinic1->id, $clinic2->id]);
});

test('clinic search response has correct structure', function () {
    $clinic = Clinic::factory()->create(['name' => 'Amsterdam Clinic']);

    $response = $this->getJson(route('admin.clinics.search', [
        'query' => 'Amsterdam',
    ]));

    $response->assertOk();
    $data = $response->json();

    expect($data)->toBeArray()
        ->and($data)->not->toBeEmpty();

    $firstItem = $data[0];
    expect($firstItem)->toHaveKeys(['id', 'name', 'label'])
        ->and($firstItem['name'])->toBe($clinic->name)
        ->and($firstItem['label'])->toBe($clinic->name);
});

test('clinic search is case insensitive', function () {
    $clinic1 = Clinic::factory()->create(['name' => 'Amsterdam Clinic']);
    $clinic2 = Clinic::factory()->create(['name' => 'amsterdam medical center']);

    // Search with lowercase
    $response = $this->performSearch('admin.clinics.search', ['query' => 'amsterdam']);
    $this->assertEntityFound($response, $clinic1->id);
    $this->assertEntityFound($response, $clinic2->id);

    // Search with uppercase
    $response = $this->performSearch('admin.clinics.search', ['query' => 'AMSTERDAM']);
    $this->assertEntityFound($response, $clinic1->id);
    $this->assertEntityFound($response, $clinic2->id);
});
