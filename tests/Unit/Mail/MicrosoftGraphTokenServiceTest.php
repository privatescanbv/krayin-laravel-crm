<?php

use App\Services\Mail\MailboxConfig;
use App\Services\Mail\MicrosoftGraphTokenService;
use Illuminate\Support\Facades\Http;

function configureTestMailboxes(array $extraMailboxes = []): void
{
    config([
        'mail.mailboxes' => array_merge([
            'privatescan' => [
                'address' => 'crm@example.com',
                'graph'   => [
                    'tenant_id'     => 'test-tenant',
                    'client_id'     => 'test-client',
                    'client_secret' => 'test-secret',
                ],
            ],
            'herniapoli' => [
                'address' => 'hp@example.com',
                'graph'   => [
                    'tenant_id'     => 'hp-tenant',
                    'client_id'     => 'hp-client',
                    'client_secret' => 'hp-secret',
                ],
            ],
        ], $extraMailboxes),
    ]);
}

beforeEach(function () {
    configureTestMailboxes();
});

test('fetches token from Microsoft on first call', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'my-token', 'expires_in' => 3600]),
    ]);

    $service = new MicrosoftGraphTokenService;

    expect($service->getAccessToken('privatescan'))->toBe('my-token');
    Http::assertSentCount(1);
});

test('returns cached token without re-fetching when still valid', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'my-token', 'expires_in' => 3600]),
    ]);

    $service = new MicrosoftGraphTokenService;
    $service->getAccessToken('privatescan');
    $service->getAccessToken('privatescan');

    Http::assertSentCount(1);
});

test('re-fetches token when it has expired', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::sequence()
            ->push(['access_token' => 'old-token', 'expires_in' => 600])
            ->push(['access_token' => 'new-token', 'expires_in' => 600]),
    ]);

    $service = new MicrosoftGraphTokenService;
    expect($service->getAccessToken('privatescan'))->toBe('old-token');

    // 600s lifetime minus the 60s renewal margin.
    test()->travel(541)->seconds();

    expect($service->getAccessToken('privatescan'))->toBe('new-token');
    Http::assertSentCount(2);
});

test('clearToken forces a fresh fetch on next call', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::sequence()
            ->push(['access_token' => 'first-token', 'expires_in' => 3600])
            ->push(['access_token' => 'second-token', 'expires_in' => 3600]),
    ]);

    $service = new MicrosoftGraphTokenService;
    $service->getAccessToken('privatescan');
    $service->clearToken('privatescan');

    expect($service->getAccessToken('privatescan'))->toBe('second-token');
    Http::assertSentCount(2);
});

test('keeps the token until the renewal margin is reached', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
    ]);

    $service = new MicrosoftGraphTokenService;
    $service->getAccessToken('privatescan');

    test()->travel(3539)->seconds();

    expect($service->getAccessToken('privatescan'))->toBe('tok');
    Http::assertSentCount(1);
});

test('fetches separate tokens per mailbox credentials', function () {
    Http::fake([
        'login.microsoftonline.com/test-tenant/*' => Http::response(['access_token' => 'ps-token', 'expires_in' => 3600]),
        'login.microsoftonline.com/hp-tenant/*'   => Http::response(['access_token' => 'hp-token', 'expires_in' => 3600]),
    ]);

    $service = new MicrosoftGraphTokenService;

    expect($service->getAccessToken('privatescan'))->toBe('ps-token')
        ->and($service->getAccessToken('herniapoli'))->toBe('hp-token');

    Http::assertSentCount(2);
});

test('getAccessTokenForAddress resolves mailbox credentials', function () {
    Http::fake([
        'login.microsoftonline.com/hp-tenant/*' => Http::response(['access_token' => 'hp-token', 'expires_in' => 3600]),
    ]);

    $service = new MicrosoftGraphTokenService;

    expect($service->getAccessTokenForAddress('hp@example.com'))->toBe('hp-token');
});

test('mailbox config resolves key by address', function () {
    expect(MailboxConfig::resolveKeyByAddress('hp@example.com'))->toBe('herniapoli')
        ->and(MailboxConfig::graphCredentials('herniapoli')['tenant_id'])->toBe('hp-tenant');
});

test('throws when graph credentials are incomplete', function () {
    config([
        'mail.mailboxes' => [
            'privatescan' => [
                'address' => 'service@privatescan.nl',
                'graph'   => [
                    'tenant_id'     => 'test-tenant',
                    'client_id'     => 'test-client',
                    'client_secret' => null,
                ],
            ],
        ],
    ]);

    $service = new MicrosoftGraphTokenService;

    expect(fn () => $service->getAccessToken('privatescan'))
        ->toThrow(InvalidArgumentException::class, 'Mailbox [privatescan] has incomplete Microsoft Graph credentials.');
});

test('each mailbox uses only its own configured client secret', function () {
    config([
        'mail.mailboxes' => [
            'privatescan' => [
                'address' => 'service@privatescan.nl',
                'graph'   => [
                    'tenant_id'     => 'shared-tenant',
                    'client_id'     => 'shared-client',
                    'client_secret' => 'ps-secret',
                ],
            ],
            'herniapoli' => [
                'address' => 'service@herniapoli.nl',
                'graph'   => [
                    'tenant_id'     => 'shared-tenant',
                    'client_id'     => 'shared-client',
                    'client_secret' => 'hp-secret',
                ],
            ],
        ],
    ]);

    Http::fake(function ($request) {
        $secret = $request->data()['client_secret'] ?? '';

        return Http::response([
            'access_token' => $secret === 'ps-secret' ? 'ps-token' : 'hp-token',
            'expires_in'   => 3600,
        ]);
    });

    $service = new MicrosoftGraphTokenService;

    expect($service->getAccessToken('privatescan'))->toBe('ps-token')
        ->and($service->getAccessToken('herniapoli'))->toBe('hp-token');

    Http::assertSent(fn ($request) => ($request->data()['client_secret'] ?? null) === 'ps-secret');
    Http::assertSent(fn ($request) => ($request->data()['client_secret'] ?? null) === 'hp-secret');
});

test('a second process reuses the cached token instead of asking Microsoft again', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'my-token', 'expires_in' => 3600]),
    ]);

    (new MicrosoftGraphTokenService)->getAccessToken('privatescan');

    // A fresh instance stands in for the next scheduled artisan run: without the shared cache that
    // run would open another TLS connection to login.microsoftonline.com, every single minute.
    expect((new MicrosoftGraphTokenService)->getAccessToken('privatescan'))->toBe('my-token');

    Http::assertSentCount(1);
});

test('clearToken invalidates the token for a process that never fetched it', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::sequence()
            ->push(['access_token' => 'first-token', 'expires_in' => 3600])
            ->push(['access_token' => 'second-token', 'expires_in' => 3600]),
    ]);

    (new MicrosoftGraphTokenService)->getAccessToken('privatescan');

    (new MicrosoftGraphTokenService)->clearToken('privatescan');

    expect((new MicrosoftGraphTokenService)->getAccessToken('privatescan'))->toBe('second-token');
});

test('a failed TLS connection is retried instead of surfacing', function () {
    Http::fakeSequence()
        ->pushResponse(Http::failedConnection('cURL error 28: SSL connection timeout'))
        ->push(['access_token' => 'token-after-retry', 'expires_in' => 3600]);

    expect((new MicrosoftGraphTokenService)->getAccessToken('privatescan'))
        ->toBe('token-after-retry');
});

test('rejected credentials throw with mailbox context and are not retried', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['error' => 'invalid_client'], 401),
    ]);

    expect(fn () => (new MicrosoftGraphTokenService)->getAccessToken('privatescan'))
        ->toThrow(Exception::class, 'Failed to get access token for mailbox [privatescan]');

    Http::assertSentCount(1);
});
