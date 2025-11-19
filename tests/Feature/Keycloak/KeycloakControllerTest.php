<?php

namespace Tests\Feature\Keycloak;

use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\Feature\Keycloak\Helpers\KeycloakHttpHelpers;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    Http::preventStrayRequests();
    KeycloakHttpHelpers::setupConfig([
        'client_secret'   => 'test-secret',
        'default_role_id' => 1,
    ]);

    $this->seed(TestSeeder::class);

    $this->role = Role::query()->first() ?? Role::factory()->create();
    $this->user = User::factory()->create([
        'role_id' => $this->role->id,
        'status'  => 1,
    ]);
});

it('redirects to dashboard if already authenticated', function () {
    $this->actingAs($this->user, 'user')
        ->get(route('admin.keycloak.redirect'))
        ->assertRedirect(route('admin.dashboard.index'));
});

it('redirects to Keycloak login when not authenticated', function () {
    Http::fake([
        'localhost:8085/realms/crm/protocol/openid-connect/auth*' => Http::response('', 302, [
            'Location' => 'http://localhost:8085/realms/crm/protocol/openid-connect/auth?client_id=crm-app&redirect_uri=...',
        ]),
    ]);

    $response = $this->get(route('admin.keycloak.redirect'));

    $response->assertStatus(302);
    expect($response->headers->get('Location'))->toContain('localhost:8085');
});

it('redirects to dashboard if already authenticated on callback', function () {
    $this->actingAs($this->user, 'user')
        ->get(route('admin.keycloak.callback', ['code' => 'test-code', 'state' => 'test-state']))
        ->assertRedirect(route('admin.dashboard.index'));
});

it('redirects to login on callback without code', function () {
    $response = $this->get(route('admin.keycloak.callback'));

    $response->assertRedirect(route('admin.session.create'));
    $response->assertSessionHas('error');
});

it('redirects to login on callback with error', function () {
    $response = $this->get(route('admin.keycloak.callback', [
        'error'             => 'access_denied',
        'error_description' => 'User cancelled login',
    ]));

    $response->assertRedirect(route('admin.session.create'));
    $response->assertSessionHas('error');
});

it('handles successful SSO callback and creates user', function () {
    // Mock Socialite user
    $socialiteUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
    $socialiteUser->shouldReceive('getId')->andReturn('keycloak-user-123');
    $socialiteUser->shouldReceive('getEmail')->andReturn('newuser@example.com');
    $socialiteUser->shouldReceive('getName')->andReturn('New User');
    $socialiteUser->user = [
        'given_name'  => 'New',
        'family_name' => 'User',
    ];

    $socialiteMock = Mockery::mock();
    $socialiteMock->shouldReceive('setBaseUrl')->andReturnSelf();
    $socialiteMock->shouldReceive('setInternalBaseUrl')->andReturnSelf();
    $socialiteMock->shouldReceive('setRealm')->andReturnSelf();
    $socialiteMock->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')
        ->with('keycloak')
        ->andReturn($socialiteMock);

    $response = $this->get(route('admin.keycloak.callback', [
        'code'  => 'test-code',
        'state' => 'test-state',
    ]));

    $response->assertRedirect(route('admin.dashboard.index'));

    // Check user was created
    $this->assertDatabaseHas('users', [
        'email'            => 'newuser@example.com',
        'keycloak_user_id' => 'keycloak-user-123',
    ]);
});

it('handles SSO callback and updates existing user', function () {
    $existingUser = User::factory()->create([
        'email'            => 'existing-controller@example.com',
        'keycloak_user_id' => null,
        'role_id'          => $this->role->id,
        'status'           => 1,
    ]);

    // Mock Socialite user
    $socialiteUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
    $socialiteUser->shouldReceive('getId')->andReturn('keycloak-user-456');
    $socialiteUser->shouldReceive('getEmail')->andReturn('existing-controller@example.com');
    $socialiteUser->shouldReceive('getName')->andReturn('Existing User');
    $socialiteUser->user = [
        'given_name'  => 'Existing',
        'family_name' => 'User',
    ];

    $socialiteMock = Mockery::mock();
    $socialiteMock->shouldReceive('setBaseUrl')->andReturnSelf();
    $socialiteMock->shouldReceive('setInternalBaseUrl')->andReturnSelf();
    $socialiteMock->shouldReceive('setRealm')->andReturnSelf();
    $socialiteMock->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')
        ->with('keycloak')
        ->andReturn($socialiteMock);

    $response = $this->get(route('admin.keycloak.callback', [
        'code'  => 'test-code',
        'state' => 'test-state',
    ]));

    $response->assertRedirect(route('admin.dashboard.index'));

    // Check user was updated
    $existingUser->refresh();
    expect($existingUser->keycloak_user_id)->toBe('keycloak-user-456');
});

it('handles backchannel logout', function () {
    $user = User::factory()->create([
        'keycloak_user_id' => 'keycloak-user-789',
        'role_id'          => $this->role->id,
        'status'           => 1,
    ]);

    // Create a mock JWT logout token
    $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode([
        'sid' => 'session-789',
        'iss' => 'http://localhost:8085/realms/crm',
        'sub' => 'keycloak-user-789',
    ]));
    $signature = 'signature';
    $logoutToken = "{$header}.{$payload}.{$signature}";

    $this->actingAs($user, 'user')
        ->post(route('admin.keycloak.backchannel-logout'), [
            'logout_token' => $logoutToken,
        ])
        ->assertStatus(200);

    // User should be logged out
    expect(auth()->guard('user')->user())->toBeNull();
});

it('handles logout callback', function () {
    $response = $this->get(route('admin.keycloak.logout-callback'));

    $response->assertRedirect(route('admin.session.create'));
});

it('redirects to standard logout route', function () {
    $response = $this->get(route('admin.keycloak.logout'));

    $response->assertRedirect(route('admin.session.destroy'));
});
