<?php

use App\Enums\KeycloakRoles;
use App\Services\Keycloak\KeycloakService;
use Illuminate\Support\Facades\Session;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    // Create a role for users
    $this->role = Role::factory()->create([
        'name'            => 'Admin',
        'description'     => 'Administrator role',
        'permission_type' => 'all',
        'permissions'     => null,
    ]);
});

it('allows access for non-Keycloak users (no keycloak_user_id)', function () {
    $user = User::factory()->create([
        'email'            => 'non-keycloak@example.com',
        'status'           => 1,
        'role_id'          => $this->role->id,
        'keycloak_user_id' => null,
    ]);

    $response = $this->actingAs($user, 'user')
        ->get(route('admin.dashboard.index'));

    $response->assertStatus(200);
});

it('allows access for Keycloak users with employee role', function () {
    $user = User::factory()->create([
        'email'            => 'employee@example.com',
        'status'           => 1,
        'role_id'          => $this->role->id,
        'keycloak_user_id' => 'test-keycloak-user-id-123',
    ]);

    // Mock rollen in sessie (zoals bij login)
    Session::put('keycloak_roles_'.$user->id, [KeycloakRoles::Employee->value]);

    $response = $this->actingAs($user, 'user')
        ->get(route('admin.dashboard.index'));

    $response->assertStatus(200);
});

it('blocks access for Keycloak users with patient role', function () {
    $user = User::factory()->create([
        'email'            => 'patient@example.com',
        'status'           => 1,
        'role_id'          => $this->role->id,
        'keycloak_user_id' => 'test-keycloak-user-id-456',
    ]);

    // Mock patient rol in sessie
    Session::put('keycloak_roles_'.$user->id, [KeycloakRoles::Patient->value]);

    $response = $this->actingAs($user, 'user')
        ->get(route('admin.dashboard.index'));

    // Check status code (401) and redirect location
    $response->assertStatus(401);
    expect($response->headers->get('Location'))->toBe(route('admin.session.create'));

    // Check that user is logged out
    expect(auth()->guard('user')->check())->toBeFalse();
});

it('allows access for Keycloak users with other roles (not patient)', function () {
    $user = User::factory()->create([
        'email'            => 'other-role@example.com',
        'status'           => 1,
        'role_id'          => $this->role->id,
        'keycloak_user_id' => 'test-keycloak-user-id-789',
    ]);

    // Mock andere rollen (geen patient)
    Session::put('keycloak_roles_'.$user->id, ['default-roles-crm', 'some-other-role']);

    $response = $this->actingAs($user, 'user')
        ->get(route('admin.dashboard.index'));

    $response->assertStatus(200);
});

it('allows access for Keycloak users with employee and other roles', function () {
    $user = User::factory()->create([
        'email'            => 'employee-plus@example.com',
        'status'           => 1,
        'role_id'          => $this->role->id,
        'keycloak_user_id' => 'test-keycloak-user-id-101',
    ]);

    // Mock employee + andere rollen
    Session::put('keycloak_roles_'.$user->id, [
        KeycloakRoles::Employee->value,
        'default-roles-crm',
        KeycloakRoles::Clinic->value,
    ]);

    $response = $this->actingAs($user, 'user')
        ->get(route('admin.dashboard.index'));

    $response->assertStatus(200);
});

it('blocks access for Keycloak users with patient role even if they have other roles', function () {
    $user = User::factory()->create([
        'email'            => 'patient-plus@example.com',
        'status'           => 1,
        'role_id'          => $this->role->id,
        'keycloak_user_id' => 'test-keycloak-user-id-202',
    ]);

    // Mock patient + andere rollen (patient heeft voorrang)
    Session::put('keycloak_roles_'.$user->id, [
        KeycloakRoles::Patient->value,
        'default-roles-crm',
        'some-other-role',
    ]);

    $response = $this->actingAs($user, 'user')
        ->get(route('admin.dashboard.index'));

    // Check status code (401) and redirect location
    $response->assertStatus(401);
    expect($response->headers->get('Location'))->toBe(route('admin.session.create'));

    // Check that user is logged out
    expect(auth()->guard('user')->check())->toBeFalse();
});

it('fetches roles from Keycloak API if not in session', function () {
    $user = User::factory()->create([
        'email'            => 'api-fetch@example.com',
        'status'           => 1,
        'role_id'          => $this->role->id,
        'keycloak_user_id' => 'test-keycloak-user-id-303',
    ]);

    // Mock KeycloakService om rollen te returnen
    $this->mock(KeycloakService::class, function ($mock) use ($user) {
        $mock->shouldReceive('getUserRoles')
            ->once()
            ->with($user->keycloak_user_id)
            ->andReturn([KeycloakRoles::Employee->value]);
    });

    // Geen rollen in sessie - moet van API gehaald worden
    $response = $this->actingAs($user, 'user')
        ->get(route('admin.dashboard.index'));

    $response->assertStatus(200);

    // Check dat rollen nu in sessie staan
    expect(Session::get('keycloak_roles_'.$user->id))->toBe([KeycloakRoles::Employee->value]);
});

it('sets employee role in session for non-Keycloak users if roles missing', function () {
    $user = User::factory()->create([
        'email'            => 'fallback@example.com',
        'status'           => 1,
        'role_id'          => $this->role->id,
        'keycloak_user_id' => null,
    ]);

    // Geen rollen in sessie
    $response = $this->actingAs($user, 'user')
        ->get(route('admin.dashboard.index'));

    $response->assertStatus(200);

    // Check dat employee rol automatisch in sessie is gezet
    expect(Session::get('keycloak_roles_'.$user->id))->toBe(['employee']);
});

it('shows error message when patient tries to access admin panel', function () {
    $user = User::factory()->create([
        'email'            => 'patient-error@example.com',
        'status'           => 1,
        'role_id'          => $this->role->id,
        'keycloak_user_id' => 'test-keycloak-user-id-404',
    ]);

    Session::put('keycloak_roles_'.$user->id, [KeycloakRoles::Patient->value]);

    $response = $this->actingAs($user, 'user')
        ->get(route('admin.dashboard.index'));

    // Check status code (401) and redirect location
    $response->assertStatus(401);
    expect($response->headers->get('Location'))->toBe(route('admin.session.create'));

    // Check that error message is in session
    $response->assertSessionHas('error', 'U heeft geen toegang tot het admin panel. Alleen medewerkers hebben toegang.');
});
