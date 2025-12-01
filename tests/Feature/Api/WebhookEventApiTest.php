<?php

namespace Tests\Feature;

use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    config(['api.keys' => ['valid-api-key-123']]);
});

test('webhooks event endpoint logs payload and returns ok', function () {
    Log::spy();

    $payload = [
        'entity_type' => 'forms',
        'id'          => 123,
        'action'      => 'created',
        'status'      => 'completed',
        'url'         => 'https://example.test/leads/123',
    ];

    $response = $this->withHeaders([
        'X-API-KEY' => 'valid-api-key-123',
        'Accept'    => 'application/json',
    ])->putJson('/api/webhooks/event', $payload);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'ok',
        ]);

    Log::shouldHaveReceived('info')
        ->once();
});
