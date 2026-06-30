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
            ->push(['access_token' => 'old-token', 'expires_in' => 3600])
            ->push(['access_token' => 'new-token', 'expires_in' => 3600]),
    ]);

    $service = new MicrosoftGraphTokenService;
    $service->getAccessToken('privatescan');

    $ref = new ReflectionClass($service);
    $prop = $ref->getProperty('expiresAt');
    $prop->setAccessible(true);
    $expiresAt = $prop->getValue($service);

    foreach (array_keys($expiresAt) as $cacheKey) {
        if (str_starts_with($cacheKey, 'privatescan:')) {
            $expiresAt[$cacheKey] = time() - 1;
        }
    }

    $prop->setValue($service, $expiresAt);

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

test('stores expires_in from token response with a 60 second buffer', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
    ]);

    $service = new MicrosoftGraphTokenService;
    $before = time();
    $service->getAccessToken('privatescan');
    $after = time();

    $ref = new ReflectionClass($service);
    $expiresAt = $ref->getProperty('expiresAt');
    $expiresAt->setAccessible(true);
    $values = $expiresAt->getValue($service);
    $value = collect($values)->first(fn ($_, $key) => str_starts_with($key, 'privatescan:'));

    expect($value)->toBeGreaterThanOrEqual($before + 3540)
        ->and($value)->toBeLessThanOrEqual($after + 3540);
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
