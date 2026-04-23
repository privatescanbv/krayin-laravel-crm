<?php

namespace Tests\Feature\Api;

use Database\Seeders\LeadChannelSeeder;
use Database\Seeders\TestSeeder;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Webkul\Lead\Models\Channel;
use Webkul\Lead\Models\Source;
use Webkul\Lead\Models\Type;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->artisan('db:seed', ['--class' => LeadChannelSeeder::class]);

    config(['api.keys' => ['valid-api-key-123']]);
});

test('api http traffic is logged with request id status and duration', function () {
    $apiHttpContexts = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$apiHttpContexts): void {
        if ($event->message === 'api_http') {
            $apiHttpContexts[] = $event->context;
        }
    });

    config([
        'api-http-log.enabled' => true,
        'api-http-log.channel' => 'single',
    ]);

    $response = $this->withHeaders([
        'X-API-KEY' => 'invalid-key',
        'Accept'    => 'application/json',
    ])->postJson('/api/leads', [
        'title' => 'x',
    ]);

    $response->assertStatus(401);
    $response->assertHeader('X-Request-Id');

    expect($apiHttpContexts)->not->toBeEmpty();
    $context = $apiHttpContexts[array_key_last($apiHttpContexts)];
    expect($context)->toHaveKeys(['request_id', 'status', 'duration_ms']);
    expect($context['status'])->toBe(401);
    expect($context['duration_ms'])->toBeFloat();
});

test('api http log redacts sensitive headers and input keys', function () {
    $apiHttpContexts = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$apiHttpContexts): void {
        if ($event->message === 'api_http') {
            $apiHttpContexts[] = $event->context;
        }
    });

    config([
        'api-http-log.enabled' => true,
        'api-http-log.channel' => 'single',
    ]);

    $source = Source::first();
    $type = Type::first();
    $channel = Channel::first();

    $this->withHeaders([
        'X-API-KEY'     => 'valid-api-key-123',
        'Authorization' => 'Bearer super-secret-token',
        'Accept'        => 'application/json',
    ])->postJson('/api/leads', [
        'first_name'      => 'John',
        'last_name'       => 'Doe',
        'email'           => 'john@example.com',
        'title'           => 'Test Lead',
        'lead_source_id'  => $source->id,
        'lead_channel_id' => $channel->id,
        'lead_type_id'    => $type->id,
        'token'           => 'must-not-appear-raw',
    ]);

    expect($apiHttpContexts)->not->toBeEmpty();
    $context = $apiHttpContexts[array_key_last($apiHttpContexts)];
    $headers = $context['request_headers'] ?? [];

    $xApiKey = $headers['X-API-KEY'] ?? $headers['x-api-key'] ?? null;
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? null;

    $body = is_string($context['request_body'] ?? null)
        ? $context['request_body']
        : json_encode($context['request_body'] ?? []);

    expect($xApiKey)->toBe(['[REDACTED]']);
    expect($auth)->toBe(['[REDACTED]']);
    expect($body)->toBeString();
    expect($body)->not->toContain('must-not-appear-raw');
    expect($body)->toContain('[REDACTED]');
});
