<?php

use App\Models\Clinic;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    // Create and authenticate a back-office user
    $this->user = User::factory()->create(['first_name' => 'Admin', 'last_name' => 'Tester']);
    $this->actingAs($this->user, 'user');
    $this->withoutMiddleware(Authenticate::class);
});

test('clinic search with query parameter filters by name', function () {
    // Create clinics that should be found
    $clinicMatch = Clinic::factory()->create(['name' => 'Amsterdam Clinic']);
    $clinicPartial = Clinic::factory()->create(['name' => 'Amsterdam Medical Center']);

    // Create clinic that should NOT be found
    $clinicNoMatch = Clinic::factory()->create(['name' => 'Rotterdam Hospital']);

    // Search with query parameter
    $response = $this->getJson(route('admin.clinics.search', [
        'query' => 'Amsterdam',
    ]));

    $response->assertOk();
    $data = $response->json();

    expect($data)->toBeArray();
    $ids = collect($data)->pluck('id')->toArray();

    expect($ids)->toContain($clinicMatch->id)
        ->and($ids)->toContain($clinicPartial->id)
        ->and($ids)->not->toContain($clinicNoMatch->id);
});

test('clinic search returns empty array when no matches', function () {
    Clinic::factory()->create(['name' => 'Amsterdam Clinic']);
    Clinic::factory()->create(['name' => 'Rotterdam Hospital']);

    $response = $this->getJson(route('admin.clinics.search', [
        'query' => 'NonExistent',
    ]));

    $response->assertOk();
    $data = $response->json();

    expect($data)->toBeArray()
        ->and($data)->toBeEmpty();
});

test('clinic search returns all when query is empty', function () {
    $clinic1 = Clinic::factory()->create(['name' => 'Amsterdam Clinic']);
    $clinic2 = Clinic::factory()->create(['name' => 'Rotterdam Hospital']);

    $response = $this->getJson(route('admin.clinics.search'));

    $response->assertOk();
    $data = $response->json();

    expect($data)->toBeArray();
    $ids = collect($data)->pluck('id')->toArray();

    expect($ids)->toContain($clinic1->id)
        ->and($ids)->toContain($clinic2->id);
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
    $response = $this->getJson(route('admin.clinics.search', [
        'query' => 'amsterdam',
    ]));

    $response->assertOk();
    $data = $response->json();
    $ids = collect($data)->pluck('id')->toArray();

    expect($ids)->toContain($clinic1->id)
        ->and($ids)->toContain($clinic2->id);

    // Search with uppercase
    $response = $this->getJson(route('admin.clinics.search', [
        'query' => 'AMSTERDAM',
    ]));

    $response->assertOk();
    $data = $response->json();
    $ids = collect($data)->pluck('id')->toArray();

    expect($ids)->toContain($clinic1->id)
        ->and($ids)->toContain($clinic2->id);
});
