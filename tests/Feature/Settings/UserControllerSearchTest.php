<?php

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

test('user search with query parameter filters by first name', function () {
    // Create users that should be found
    $userMatch = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
    $userPartial = User::factory()->create(['first_name' => 'Johnny', 'last_name' => 'Smith']);

    // Create user that should NOT be found
    $userNoMatch = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);

    // Search with search parameter (RequestCriteria uses 'search' parameter)
    $response = $this->getJson(route('admin.settings.users.search', [
        'search'       => 'John',
        'searchFields' => 'first_name:like;last_name:like',
    ]));

    $response->assertOk();
    $data = $response->json('data');

    expect($data)->toBeArray();
    $ids = collect($data)->pluck('id')->toArray();

    expect($ids)->toContain($userMatch->id)
        ->and($ids)->toContain($userPartial->id)
        ->and($ids)->not->toContain($userNoMatch->id);
});

test('user search with query parameter filters by last name', function () {
    $userMatch = User::factory()->create(['first_name' => 'John', 'last_name' => 'Smith']);
    $userPartial = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Smithson']);

    $userNoMatch = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);

    $response = $this->getJson(route('admin.settings.users.search', [
        'search'       => 'Smith',
        'searchFields' => 'first_name:like;last_name:like',
    ]));

    $response->assertOk();
    $data = $response->json('data');

    expect($data)->toBeArray();
    $ids = collect($data)->pluck('id')->toArray();

    expect($ids)->toContain($userMatch->id)
        ->and($ids)->toContain($userPartial->id)
        ->and($ids)->not->toContain($userNoMatch->id);
});

test('user search with query parameter filters by email', function () {
    $userMatch = User::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'email'      => 'john.doe@example.com',
    ]);

    $userNoMatch = User::factory()->create([
        'first_name' => 'Jane',
        'last_name'  => 'Smith',
        'email'      => 'jane.smith@example.com',
    ]);

    $response = $this->getJson(route('admin.settings.users.search', [
        'search'       => 'john.doe',
        'searchFields' => 'email:like',
    ]));

    $response->assertOk();
    $data = $response->json('data');

    expect($data)->toBeArray();
    $ids = collect($data)->pluck('id')->toArray();

    expect($ids)->toContain($userMatch->id)
        ->and($ids)->not->toContain($userNoMatch->id);
});

test('user search returns empty array when no matches', function () {
    User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
    User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Smith']);

    $response = $this->getJson(route('admin.settings.users.search', [
        'search'       => 'NonExistent',
        'searchFields' => 'first_name:like;last_name:like',
    ]));

    $response->assertOk();
    $data = $response->json('data');

    expect($data)->toBeArray()
        ->and($data)->toBeEmpty();
});

test('user search returns all when query is empty', function () {
    $user1 = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
    $user2 = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Smith']);

    $response = $this->getJson(route('admin.settings.users.search'));

    $response->assertOk();
    $data = $response->json('data');

    expect($data)->toBeArray();
    $ids = collect($data)->pluck('id')->toArray();

    expect($ids)->toContain($user1->id)
        ->and($ids)->toContain($user2->id);
});

test('user search response has correct structure', function () {
    $user = User::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'email'      => 'john.doe@example.com',
    ]);

    $response = $this->getJson(route('admin.settings.users.search', [
        'search'       => 'John',
        'searchFields' => 'first_name:like;last_name:like',
    ]));

    $response->assertOk();
    $data = $response->json('data');

    expect($data)->toBeArray()
        ->and($data)->not->toBeEmpty();

    $firstItem = $data[0];
    expect($firstItem)->toHaveKeys(['id', 'first_name', 'last_name', 'name', 'email'])
        ->and($firstItem['first_name'])->toBe($user->first_name)
        ->and($firstItem['last_name'])->toBe($user->last_name)
        ->and($firstItem['email'])->toBe($user->email);
});

test('user search is case insensitive', function () {
    $user1 = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
    $user2 = User::factory()->create(['first_name' => 'johnny', 'last_name' => 'Smith']);

    // Search with lowercase
    $response = $this->getJson(route('admin.settings.users.search', [
        'search'       => 'john',
        'searchFields' => 'first_name:like;last_name:like',
    ]));

    $response->assertOk();
    $data = $response->json('data');
    $ids = collect($data)->pluck('id')->toArray();

    expect($ids)->toContain($user1->id)
        ->and($ids)->toContain($user2->id);

    // Search with uppercase
    $response = $this->getJson(route('admin.settings.users.search', [
        'search'       => 'JOHN',
        'searchFields' => 'first_name:like;last_name:like',
    ]));

    $response->assertOk();
    $data = $response->json('data');
    $ids = collect($data)->pluck('id')->toArray();

    expect($ids)->toContain($user1->id)
        ->and($ids)->toContain($user2->id);
});

test('user search with search parameter filters by first_name', function () {
    $userMatch = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
    $userNoMatch = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);

    $response = $this->getJson(route('admin.settings.users.search', [
        'search'       => 'first_name:John',
        'searchFields' => 'first_name:like',
    ]));

    $response->assertOk();
    $data = $response->json('data');
    $ids = collect($data)->pluck('id')->toArray();

    expect($ids)->toContain($userMatch->id)
        ->and($ids)->not->toContain($userNoMatch->id);
});

test('user search with search parameter filters by email', function () {
    $userMatch = User::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'email'      => 'john.doe@example.com',
    ]);

    $userNoMatch = User::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Smith',
        'email'      => 'john.smith@example.com',
    ]);

    $response = $this->getJson(route('admin.settings.users.search', [
        'search'       => 'email:john.doe@example.com',
        'searchFields' => 'email:=',
    ]));

    $response->assertOk();
    $data = $response->json('data');
    $ids = collect($data)->pluck('id')->toArray();

    expect($ids)->toContain($userMatch->id)
        ->and($ids)->not->toContain($userNoMatch->id);
});
