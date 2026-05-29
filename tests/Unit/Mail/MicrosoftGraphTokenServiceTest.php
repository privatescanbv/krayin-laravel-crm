<?php

use App\Services\Mail\MicrosoftGraphTokenService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'mail.graph.tenant_id'     => 'test-tenant',
        'mail.graph.client_id'     => 'test-client',
        'mail.graph.client_secret' => 'test-secret',
    ]);
});

test('fetches token from Microsoft on first call', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'my-token', 'expires_in' => 3600]),
    ]);

    $service = new MicrosoftGraphTokenService;

    expect($service->getAccessToken())->toBe('my-token');
    Http::assertSentCount(1);
});

test('returns cached token without re-fetching when still valid', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'my-token', 'expires_in' => 3600]),
    ]);

    $service = new MicrosoftGraphTokenService;
    $service->getAccessToken();
    $service->getAccessToken();

    Http::assertSentCount(1);
});

test('re-fetches token when it has expired', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::sequence()
            ->push(['access_token' => 'old-token', 'expires_in' => 3600])
            ->push(['access_token' => 'new-token', 'expires_in' => 3600]),
    ]);

    $service = new MicrosoftGraphTokenService;
    $service->getAccessToken();

    // Simulate the token having passed its expiry time
    $ref = new ReflectionClass($service);
    $prop = $ref->getProperty('expiresAt');
    $prop->setAccessible(true);
    $prop->setValue($service, time() - 1);

    expect($service->getAccessToken())->toBe('new-token');
    Http::assertSentCount(2);
});

test('clearToken forces a fresh fetch on next call', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::sequence()
            ->push(['access_token' => 'first-token', 'expires_in' => 3600])
            ->push(['access_token' => 'second-token', 'expires_in' => 3600]),
    ]);

    $service = new MicrosoftGraphTokenService;
    $service->getAccessToken();
    $service->clearToken();

    expect($service->getAccessToken())->toBe('second-token');
    Http::assertSentCount(2);
});

test('stores expires_in from token response with a 60 second buffer', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
    ]);

    $service = new MicrosoftGraphTokenService;
    $before = time();
    $service->getAccessToken();
    $after = time();

    $ref = new ReflectionClass($service);
    $expiresAt = $ref->getProperty('expiresAt');
    $expiresAt->setAccessible(true);
    $value = $expiresAt->getValue($service);

    // Should be roughly now + 3600 - 60 = now + 3540
    expect($value)->toBeGreaterThanOrEqual($before + 3540)
        ->and($value)->toBeLessThanOrEqual($after + 3540);
});
