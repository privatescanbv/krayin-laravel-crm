<?php

namespace Tests\Feature;

use Database\Seeders\LeadChannelSeeder;
use Database\Seeders\TestSeeder;
use Webkul\Lead\Models\Channel;
use Webkul\Lead\Models\Source;
use Webkul\Lead\Models\Type;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->artisan('db:seed', ['--class' => LeadChannelSeeder::class]);

    // Set up test API key
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
});

test('API request with invalid API key returns 401', function () {
    $response = test()->withHeaders([
        'X-API-KEY' => 'invalid-key',
        'Accept'    => 'application/json',
    ])->postJson('/api/leads', [
        'title'      => 'Test Lead',
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'email'      => 'john@example.com',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'error'   => 'Invalid API key',
            'message' => 'The provided API key is not valid',
        ]);
});

test('API request with valid API key works', function () {
    $source = Source::first();
    $type = Type::first();
    $channel = Channel::first();

    $leadData = [
        'first_name'      => 'John',
        'last_name'       => 'Doe',
        'email'           => 'john@example.com',
        'title'           => 'Test Lead',
        'lead_source_id'  => $source->id,
        'lead_channel_id' => $channel->id,
        'lead_type_id'    => $type->id,
    ];

    $response = $this->withHeaders([
        'X-API-KEY' => 'valid-api-key-123',
        'Accept'    => 'application/json',
    ])->postJson('/api/leads', $leadData);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Lead created successfully.',
        ]);
});

test('API request with alternative valid API key works', function () {
    $source = Source::first();
    $type = Type::first();
    $channel = Channel::first();

    $leadData = [
        'first_name'      => 'Jane',
        'last_name'       => 'Smith',
        'email'           => 'jane@example.com',
        'title'           => 'Test Lead 2',
        'lead_source_id'  => $source->id,
        'lead_channel_id' => $channel->id,
        'lead_type_id'    => $type->id,
    ];

    $response = $this->withHeaders([
        'X-API-KEY' => 'another-valid-key',
        'Accept'    => 'application/json',
    ])->postJson('/api/leads', $leadData);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Lead created successfully.',
        ]);
});

test('sales-leads endpoint requires API key', function () {
    // Test without API key
    $response = $this->postJson('/api/sales-leads', [
        'title'      => 'Test Workflow Lead',
        'first_name' => 'Test',
        'last_name'  => 'User',
    ]);
    $response->assertStatus(401);

    // Test with valid API key
    $response = $this->withHeaders([
        'X-API-KEY' => 'valid-api-key-123',
        'Accept'    => 'application/json',
    ])->postJson('/api/sales-leads', [
        'title'      => 'Test Workflow Lead',
        'first_name' => 'Test',
        'last_name'  => 'User',
    ]);

    // This might return a different status depending on validation,
    // but it should not be 401 (unauthorized)
    expect($response->getStatusCode())->not->toBe(401);
});

test('groups endpoint requires API key', function () {
    // Test without API key
    $response = $this->getJson('/api/groups/byDepartment/test');
    $response->assertStatus(401);

    // Test with valid API key
    $response = $this->withHeaders([
        'X-API-KEY' => 'valid-api-key-123',
        'Accept'    => 'application/json',
    ])->getJson('/api/groups/byDepartment/test');

    // This might return 404 or other status depending on data,
    // but it should not be 401 (unauthorized)
    expect($response->getStatusCode())->not->toBe(401);
});
