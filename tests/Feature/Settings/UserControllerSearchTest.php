<?php

use Tests\Feature\Concerns\ControllerSearchTestHelpers;
use Webkul\User\Models\User;

uses(ControllerSearchTestHelpers::class);

beforeEach(function () {
    $this->setUpSearchTest();
});

test('user search with query parameter filters by first name', function () {
    $userMatch = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
    $userPartial = User::factory()->create(['first_name' => 'Johnny', 'last_name' => 'Smith']);
    $userNoMatch = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);

    $response = $this->performSearch('admin.settings.users.search', [
        'search'       => 'John',
        'searchFields' => 'first_name:like;last_name:like',
    ]);

    $this->assertEntityFound($response, $userMatch->id);
    $this->assertEntityFound($response, $userPartial->id);
    $this->assertEntityNotFound($response, $userNoMatch->id);
});

test('user search with query parameter filters by last name', function () {
    $userMatch = User::factory()->create(['first_name' => 'John', 'last_name' => 'Smith']);
    $userPartial = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Smithson']);
    $userNoMatch = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);

    $response = $this->performSearch('admin.settings.users.search', [
        'search'       => 'Smith',
        'searchFields' => 'first_name:like;last_name:like',
    ]);

    $this->assertEntityFound($response, $userMatch->id);
    $this->assertEntityFound($response, $userPartial->id);
    $this->assertEntityNotFound($response, $userNoMatch->id);
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

    $response = $this->performSearch('admin.settings.users.search', [
        'search'       => 'john.doe',
        'searchFields' => 'email:like',
    ]);

    $this->assertEntityFound($response, $userMatch->id);
    $this->assertEntityNotFound($response, $userNoMatch->id);
});

test('user search returns empty array when no matches', function () {
    User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
    User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Smith']);

    $response = $this->performSearch('admin.settings.users.search', [
        'search'       => 'NonExistent',
        'searchFields' => 'first_name:like;last_name:like',
    ]);

    $this->assertSearchEmpty($response);
});

test('user search returns all when query is empty', function () {
    $user1 = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
    $user2 = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Smith']);

    $response = $this->performSearch('admin.settings.users.search');

    $this->assertSearchReturnsAll($response, [$user1->id, $user2->id]);
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
    $response = $this->performSearch('admin.settings.users.search', [
        'search'       => 'john',
        'searchFields' => 'first_name:like;last_name:like',
    ]);
    $this->assertEntityFound($response, $user1->id);
    $this->assertEntityFound($response, $user2->id);

    // Search with uppercase
    $response = $this->performSearch('admin.settings.users.search', [
        'search'       => 'JOHN',
        'searchFields' => 'first_name:like;last_name:like',
    ]);
    $this->assertEntityFound($response, $user1->id);
    $this->assertEntityFound($response, $user2->id);
});

test('user search with search parameter filters by first_name', function () {
    $userMatch = User::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
    $userNoMatch = User::factory()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);

    $response = $this->performSearch('admin.settings.users.search', [
        'search'       => 'first_name:John',
        'searchFields' => 'first_name:like',
    ]);

    $this->assertEntityFound($response, $userMatch->id);
    $this->assertEntityNotFound($response, $userNoMatch->id);
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

    $response = $this->performSearch('admin.settings.users.search', [
        'search'       => 'email:john.doe@example.com',
        'searchFields' => 'email:=',
    ]);

    $this->assertEntityFound($response, $userMatch->id);
    $this->assertEntityNotFound($response, $userNoMatch->id);
});
