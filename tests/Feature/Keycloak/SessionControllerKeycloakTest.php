<?php

namespace Tests\Feature\Keycloak;

use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Keycloak\Helpers\KeycloakHttpHelpers;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    Http::preventStrayRequests();
    KeycloakHttpHelpers::setupConfig();

    $this->seed(TestSeeder::class);

    $this->role = Role::query()->first() ?? Role::factory()->create();
});

/**
 * Create a user without triggering UserObserver.
 */
function createUser(array $attributes = []): User
{
    KeycloakHttpHelpers::isolateTest();
    $user = User::factory()->create($attributes);
    KeycloakHttpHelpers::enableKeycloak();

    return $user->refresh();
}

it('redirects SSO user to Keycloak logout on logout', function () {
    $user = createUser([
        'email'            => 'sso-logout@example.com',
        'keycloak_user_id' => 'keycloak-user-123',
        'role_id'          => $this->role->id,
        'status'           => 1,
    ]);
    $response = $this->actingAs($user, 'user')
        ->withSession([
            'auth_source' => 'keycloak',
        ])
        ->delete(route('admin.session.destroy'));

    $response->assertStatus(302);
    $location = $response->headers->get('Location');
    expect($location)->toContain('test-keycloak.local:9999')
        ->and($location)->toContain('/protocol/openid-connect/logout')
        ->and($location)->toContain('client_id=crm-app')
        ->and(auth()->guard('user')->user())->toBeNull();
});

it('redirects SSO user to Keycloak logout on logout within ', function () {
    $user = createUser([
        'email'            => 'sso-logout@example.com',
        'keycloak_user_id' => 'keycloak-user-123',
        'role_id'          => $this->role->id,
        'status'           => 1,
    ]);
    //    session('auth_source', 'keycloak');
    $response = $this->actingAs($user, 'user')
        ->withSession([
            'auth_source' => 'keycloak',
        ])
        ->delete(route('admin.session.destroy'));

    $response->assertStatus(302);
    $location = $response->headers->get('Location');
    expect($location)->toContain('test-keycloak.local:9999')
        ->and($location)->toContain('/protocol/openid-connect/logout')
        ->and($location)->toContain('client_id=crm-app')
        ->and(auth()->guard('user')->user())->toBeNull();
});

it('redirects non-SSO user to login on logout', function () {
    $user = createUser([
        'email'            => 'regular@example.com',
        'keycloak_user_id' => null,
        'role_id'          => $this->role->id,
        'status'           => 1,
    ]);

    $response = $this->actingAs($user, 'user')
        ->delete(route('admin.session.destroy'));

    $response->assertRedirect(route('admin.session.create'));
    expect(auth()->guard('user')->user())->toBeNull();
});

it('redirects SSO user to login when client_id is not configured', function () {
    $user = createUser([
        'email'            => 'sso-no-client@example.com',
        'keycloak_user_id' => 'keycloak-user-123',
        'role_id'          => $this->role->id,
        'status'           => 1,
    ]);

    // Disable Keycloak after user creation
    Config::set('services.keycloak.client_id', null);
    expect(config('services.keycloak.client_id'))->toBeNull();

    $response = $this->actingAs($user, 'user')
        ->delete(route('admin.session.destroy'));

    $response->assertRedirect(route('admin.session.create'));
});

it('prevents setting intended URL for logout routes', function () {
    createUser([
        'email'   => 'test@example.com',
        'role_id' => $this->role->id,
        'status'  => 1,
    ]);

    $response = $this->from(route('admin.session.destroy'))
        ->get(route('admin.session.create'));

    $response->assertOk();
    expect(session()->get('url.intended'))->not->toContain('logout');
});
