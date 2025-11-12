<?php

use App\Models\ResourceType;
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

test('resource type search with query parameter filters by name', function () {
    // Create resource types that should be found
    $resourceTypeMatch = ResourceType::where('name', 'LIKE', 'Artsen')->firstOrFail();
    $resourceTypePartial = ResourceType::factory()->create(['name' => 'Artsen Specialist']);

    // Create resource type that should NOT be found
    $resourceTypeNoMatch = ResourceType::factory()->create(['name' => 'MRI Scanner']);

    // Search with query parameter
    $response = $this->getJson(route('admin.settings.resource_types.search', [
        'query' => 'Arts',
    ]));

    $response->assertOk();
    $data = $response->json();

    expect($data)->toBeArray();
    $ids = collect($data)->pluck('id')->toArray();

    expect($ids)->toContain($resourceTypeMatch->id)
        ->and($ids)->toContain($resourceTypePartial->id)
        ->and($ids)->not->toContain($resourceTypeNoMatch->id);
});

test('resource type search returns empty array when no matches', function () {
    ResourceType::factory()->create(['name' => 'MRI Scanner']);
    ResourceType::factory()->create(['name' => 'CT Scanner']);

    $response = $this->getJson(route('admin.settings.resource_types.search', [
        'query' => 'NonExistent',
    ]));

    $response->assertOk();
    $data = $response->json();

    expect($data)->toBeArray()
        ->and($data)->toBeEmpty();
});

test('resource type search returns all when query is empty', function () {

    $resourceType1 = ResourceType::where('name', 'LIKE', 'Artsen')->firstOrFail();
    $resourceType2 = ResourceType::factory()->create(['name' => 'MRI Scanner']);

    $response = $this->getJson(route('admin.settings.resource_types.search'));

    $response->assertOk();
    $data = $response->json();

    expect($data)->toBeArray();
    $ids = collect($data)->pluck('id')->toArray();

    expect($ids)->toContain($resourceType1->id)
        ->and($ids)->toContain($resourceType2->id);
});

test('resource type search response has correct structure', function () {
    $resourceType = ResourceType::where('name', 'LIKE', 'Artsen')->firstOrFail();

    $response = $this->getJson(route('admin.settings.resource_types.search', [
        'query' => 'Arts',
    ]));

    $response->assertOk();
    $data = $response->json();

    expect($data)->toBeArray()
        ->and($data)->not->toBeEmpty();

    $firstItem = $data[0];
    expect($firstItem)->toHaveKeys(['id', 'name', 'label'])
        ->and($firstItem['name'])->toBe($resourceType->name)
        ->and($firstItem['label'])->toBe($resourceType->name);
});
