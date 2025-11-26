<?php

namespace Tests\Feature\Keycloak;

use App\Services\Keycloak\KeycloakService;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Keycloak\Helpers\KeycloakHttpHelpers;

beforeEach(function () {
    Http::preventStrayRequests();
    KeycloakHttpHelpers::setupConfig();
});

it('can get base URL', function () {
    $service = app(KeycloakService::class);

    expect($service->getExternalBaseUrl())->toBe('http://test-keycloak.local:9999');
});

it('can get realm', function () {
    $service = app(KeycloakService::class);

    expect($service->getRealm())->toBe('crm');
});

it('can get client ID', function () {
    $service = app(KeycloakService::class);

    expect($service->getClientId())->toBe('crm-app');
});

it('can get admin token', function () {
    KeycloakHttpHelpers::fakeAdminToken();

    $service = app(KeycloakService::class);
    $token = $service->getAdminToken();

    expect($token)->toBe('test-access-token');
});

it('returns null when admin token request fails', function () {
    KeycloakHttpHelpers::fakeAdminToken('', 401);

    $service = app(KeycloakService::class);
    $token = $service->getAdminToken();

    expect($token)->toBeNull();
});

it('can get user by email', function () {
    KeycloakHttpHelpers::fakeAdminToken();
    KeycloakHttpHelpers::fakeUserOperations([
        'email_checks' => [
            'test@example.com' => [
                'id'        => 'user-123',
                'email'     => 'test@example.com',
                'firstName' => 'Test',
                'lastName'  => 'User',
            ],
        ],
    ]);

    $service = app(KeycloakService::class);
    $user = $service->getUserByEmail('test@example.com');

    expect($user)->not->toBeNull();
    expect($user['id'])->toBe('user-123');
    expect($user['email'])->toBe('test@example.com');
});

it('returns null when user not found by email', function () {
    KeycloakHttpHelpers::fakeAdminToken();
    KeycloakHttpHelpers::fakeUserOperations([
        'email_checks' => ['notfound@example.com' => null],
    ]);

    $service = app(KeycloakService::class);
    $user = $service->getUserByEmail('notfound@example.com');

    expect($user)->toBeNull();
});

it('can create user', function () {
    KeycloakHttpHelpers::fakeAdminToken();
    KeycloakHttpHelpers::fakeUserOperations([
        'create_responses' => ['new-user-123'],
    ]);

    $service = app(KeycloakService::class);
    $userId = $service->createUser([
        'username'  => 'test@example.com',
        'email'     => 'test@example.com',
        'firstName' => 'Test',
        'lastName'  => 'User',
        'enabled'   => true,
    ]);

    expect($userId)->toBe('new-user-123');
});

it('can set user password', function () {
    KeycloakHttpHelpers::fakeAdminToken();
    KeycloakHttpHelpers::fakeUserOperations();

    $service = app(KeycloakService::class);
    $result = $service->setUserPassword('user-123', 'new-password', false);

    expect($result)->toBeTrue();
});

it('can update user', function () {
    KeycloakHttpHelpers::fakeAdminToken();
    KeycloakHttpHelpers::fakeUserOperations();

    $service = app(KeycloakService::class);
    $result = $service->updateUser('user-123', [
        'firstName' => 'Updated',
        'lastName'  => 'Name',
    ]);

    expect($result)->toBeTrue();
});

it('can delete user', function () {
    KeycloakHttpHelpers::fakeAdminToken();
    KeycloakHttpHelpers::fakeUserOperations();

    $service = app(KeycloakService::class);
    $result = $service->deleteUser('user-123');

    expect($result)->toBeTrue();
});

it('can get user by ID', function () {
    KeycloakHttpHelpers::fakeAdminToken();
    KeycloakHttpHelpers::fakeUserOperations([
        'user_by_id' => [
            'user-123' => [
                'id'        => 'user-123',
                'email'     => 'test@example.com',
                'firstName' => 'Test',
                'lastName'  => 'User',
            ],
        ],
    ]);

    $service = app(KeycloakService::class);
    $user = $service->getUserById('user-123');

    expect($user)->not->toBeNull();
    expect($user['id'])->toBe('user-123');
});

it('can decode logout token', function () {
    // Create a mock JWT token
    $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode([
        'sid' => 'session-123',
        'iss' => 'http://test-keycloak.local:9999/realms/crm',
        'sub' => 'user-123',
    ]));
    $signature = 'signature';
    $token = "{$header}.{$payload}.{$signature}";

    $service = app(KeycloakService::class);
    $decoded = $service->decodeLogoutToken($token);

    expect($decoded)->not->toBeNull();
    expect($decoded['session_id'])->toBe('session-123');
    expect($decoded['issuer'])->toBe('http://test-keycloak.local:9999/realms/crm');
    expect($decoded['subject'])->toBe('user-123');
});

it('can get logout URL', function () {
    $service = app(KeycloakService::class);
    $logoutUrl = $service->getLogoutUrl();

    expect($logoutUrl)->toBe('http://test-keycloak.local:9999/realms/crm/protocol/openid-connect/logout');
});
