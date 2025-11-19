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
    KeycloakHttpHelpers::setupConfig([
        'default_role_id' => 1,
    ]);

    $this->seed(TestSeeder::class);
    $this->role = Role::query()->first() ?? Role::factory()->create();
});

describe('UserObserver - Create', function () {
    it('creates user in Keycloak when user is created with active status', function () {
        KeycloakHttpHelpers::fakeAdminToken();
        KeycloakHttpHelpers::fakeUserOperations([
            'email_checks'     => ['newuser@example.com' => null],
            'create_responses' => ['keycloak-user-123'],
        ]);

        $user = User::factory()->create([
            'email'   => 'newuser@example.com',
            'status'  => 1,
            'role_id' => $this->role->id,
        ]);

        // Check keycloak_user_id was set
        $user->refresh();
        expect($user->keycloak_user_id)->toBe('keycloak-user-123');
    });

    it('does not create user in Keycloak when user is created with inactive status', function () {
        KeycloakHttpHelpers::fakeAdminToken();

        $user = User::factory()->create([
            'email'   => 'inactive@example.com',
            'status'  => 0,
            'role_id' => $this->role->id,
        ]);

        // Check keycloak_user_id was not set
        $user->refresh();
        expect($user->keycloak_user_id)->toBeNull();
    });

    it('does not create user in Keycloak when user has no email', function () {
        KeycloakHttpHelpers::fakeAdminToken();

        $user = User::factory()->create([
            'email'   => '',
            'status'  => 1,
            'role_id' => $this->role->id,
        ]);

        // Check keycloak_user_id was not set
        $user->refresh();
        expect($user->keycloak_user_id)->toBeNull();
    });

    it('does not create user in Keycloak when Keycloak is not configured', function () {
        Config::set('services.keycloak.client_id');

        $user = User::factory()->create([
            'email'   => 'newuser@example.com',
            'status'  => 1,
            'role_id' => $this->role->id,
        ]);

        // Check keycloak_user_id was not set
        $user->refresh();
        expect($user->keycloak_user_id)->toBeNull();
    });

    it('uses plaintext password when creating user', function () {
        KeycloakHttpHelpers::fakeAdminToken();
        KeycloakHttpHelpers::fakeUserOperations([
            'email_checks'     => ['passworduser@example.com' => null],
            'create_responses' => ['keycloak-user-456'],
        ]);

        $user = User::factory()->create([
            'email'    => 'passworduser@example.com',
            'password' => 'plaintext-password-123',
            'status'   => 1,
            'role_id'  => $this->role->id,
        ]);

        // Check keycloak_user_id was set
        $user->refresh();
        expect($user->keycloak_user_id)->toBe('keycloak-user-456');
    });
});

describe('UserObserver - Update', function () {
    it('updates user in Keycloak when email changes', function () {
        $user = User::factory()->create([
            'email'            => 'original@example.com',
            'keycloak_user_id' => 'keycloak-user-789',
            'status'           => 1,
            'role_id'          => $this->role->id,
        ]);

        KeycloakHttpHelpers::fakeAdminToken();
        KeycloakHttpHelpers::fakeUserOperations([
            'user_by_id' => [
                'keycloak-user-789' => [
                    'id'    => 'keycloak-user-789',
                    'email' => 'original@example.com',
                ],
            ],
        ]);

        $user->update(['email' => 'updated@example.com']);

        // User should still have keycloak_user_id
        $user->refresh();
        expect($user->keycloak_user_id)->toBe('keycloak-user-789');
    });

    it('updates user in Keycloak when first_name changes', function () {
        $user = User::factory()->create([
            'email'            => 'nameuser@example.com',
            'first_name'       => 'John',
            'keycloak_user_id' => 'keycloak-user-101',
            'status'           => 1,
            'role_id'          => $this->role->id,
        ]);

        KeycloakHttpHelpers::fakeAdminToken();
        KeycloakHttpHelpers::fakeUserOperations([
            'user_by_id' => [
                'keycloak-user-101' => [
                    'id'    => 'keycloak-user-101',
                    'email' => 'nameuser@example.com',
                ],
            ],
        ]);

        $user->update(['first_name' => 'Jane']);

        // User should still have keycloak_user_id
        $user->refresh();
        expect($user->keycloak_user_id)->toBe('keycloak-user-101');
    });

    it('updates user in Keycloak when last_name changes', function () {
        $user = User::factory()->create([
            'email'            => 'lastnameuser@example.com',
            'last_name'        => 'Doe',
            'keycloak_user_id' => 'keycloak-user-102',
            'status'           => 1,
            'role_id'          => $this->role->id,
        ]);

        KeycloakHttpHelpers::fakeAdminToken();
        KeycloakHttpHelpers::fakeUserOperations([
            'user_by_id' => [
                'keycloak-user-102' => [
                    'id'    => 'keycloak-user-102',
                    'email' => 'lastnameuser@example.com',
                ],
            ],
        ]);

        $user->update(['last_name' => 'Smith']);

        // User should still have keycloak_user_id
        $user->refresh();
        expect($user->keycloak_user_id)->toBe('keycloak-user-102');
    });

    it('updates password in Keycloak when password changes', function () {
        $user = User::factory()->create([
            'email'            => 'passwordupdate@example.com',
            'keycloak_user_id' => 'keycloak-user-103',
            'status'           => 1,
            'role_id'          => $this->role->id,
        ]);

        KeycloakHttpHelpers::fakeAdminToken();
        KeycloakHttpHelpers::fakeUserOperations([
            'user_by_id' => [
                'keycloak-user-103' => [
                    'id'    => 'keycloak-user-103',
                    'email' => 'passwordupdate@example.com',
                ],
            ],
        ]);

        $user->update(['password' => 'new-password-123']);

        // User should still have keycloak_user_id
        $user->refresh();
        expect($user->keycloak_user_id)->toBe('keycloak-user-103');
    });

    it('deletes user from Keycloak when status changes to inactive', function () {
        $user = User::factory()->create([
            'email'            => 'deactivate@example.com',
            'keycloak_user_id' => 'keycloak-user-104',
            'status'           => 1,
            'role_id'          => $this->role->id,
        ]);

        KeycloakHttpHelpers::fakeAdminToken();
        KeycloakHttpHelpers::fakeUserOperations([
            'user_by_id' => [
                'keycloak-user-104' => [
                    'id'    => 'keycloak-user-104',
                    'email' => 'deactivate@example.com',
                ],
            ],
        ]);

        $user->update(['status' => 0]);

        // keycloak_user_id should be cleared after deletion from Keycloak
        // When user is reactivated, a new user will be created in Keycloak with a new ID
        $user->refresh();
        expect($user->keycloak_user_id)->toBeNull();
    });

    it('creates user in Keycloak when status changes to active', function () {
        $user = User::factory()->create([
            'email'            => 'activate@example.com',
            'keycloak_user_id' => null,
            'status'           => 0,
            'role_id'          => $this->role->id,
        ]);

        KeycloakHttpHelpers::fakeAdminToken();
        KeycloakHttpHelpers::fakeUserOperations([
            'email_checks'     => ['activate@example.com' => null],
            'create_responses' => ['keycloak-user-105'],
        ]);

        $user->update(['status' => 1]);

        // keycloak_user_id should be set
        $user->refresh();
        expect($user->keycloak_user_id)->toBe('keycloak-user-105');
    });

    it('does not update Keycloak when irrelevant fields change', function () {
        $user = User::factory()->create([
            'email'            => 'irrelevant@example.com',
            'keycloak_user_id' => 'keycloak-user-106',
            'status'           => 1,
            'role_id'          => $this->role->id,
        ]);

        KeycloakHttpHelpers::fakeAdminToken();
        // No HTTP calls should be made for irrelevant fields

        // Update an irrelevant field (like image or signature)
        $user->update(['image' => 'test-image.jpg']);

        // User should still have keycloak_user_id
        $user->refresh();
        expect($user->keycloak_user_id)->toBe('keycloak-user-106');
    });
});

describe('UserObserver - Delete', function () {
    it('deletes user from Keycloak when user is deleted', function () {
        $user = User::factory()->create([
            'email'            => 'delete@example.com',
            'keycloak_user_id' => 'keycloak-user-107',
            'status'           => 1,
            'role_id'          => $this->role->id,
        ]);

        KeycloakHttpHelpers::fakeAdminToken();
        KeycloakHttpHelpers::fakeUserOperations([
            'user_by_id' => [
                'keycloak-user-107' => [
                    'id'    => 'keycloak-user-107',
                    'email' => 'delete@example.com',
                ],
            ],
        ]);

        $user->delete();

        // User should be deleted (can't refresh)
        expect(User::find($user->id))->toBeNull();
    });

    it('does not delete from Keycloak when user has no keycloak_user_id', function () {
        $user = User::factory()->create([
            'email'            => 'noexternal@example.com',
            'keycloak_user_id' => null,
            'status'           => 1,
            'role_id'          => $this->role->id,
        ]);

        KeycloakHttpHelpers::fakeAdminToken();
        // No HTTP calls should be made

        $user->delete();

        // User should be deleted
        expect(User::find($user->id))->toBeNull();
    });

    it('does not delete from Keycloak when Keycloak is not configured', function () {
        $user = User::factory()->create([
            'email'            => 'noconfig@example.com',
            'keycloak_user_id' => 'keycloak-user-108',
            'status'           => 1,
            'role_id'          => $this->role->id,
        ]);

        Config::set('services.keycloak.client_id', null);

        $user->delete();

        // User should be deleted
        expect(User::find($user->id))->toBeNull();
    });
});

describe('UserObserver - Edge Cases', function () {
    it('handles user that already exists in Keycloak gracefully', function () {
        KeycloakHttpHelpers::fakeAdminToken();
        KeycloakHttpHelpers::fakeUserOperations([
            'email_checks' => [
                'existing@example.com' => [
                    'id'    => 'existing-keycloak-id',
                    'email' => 'existing@example.com',
                ],
            ],
        ]);

        $user = User::factory()->create([
            'email'   => 'existing@example.com',
            'status'  => 1,
            'role_id' => $this->role->id,
        ]);

        // Should not set keycloak_user_id if user already exists (AddKeycloakUserAction returns error)
        $user->refresh();
        expect($user->keycloak_user_id)->toBeNull();
    });

    it('skips sync when user already has keycloak_user_id on create', function () {
        KeycloakHttpHelpers::fakeAdminToken();
        // No HTTP calls should be made

        $user = User::factory()->create([
            'email'            => 'alreadyexists@example.com',
            'keycloak_user_id' => 'keycloak-user-109',
            'status'           => 1,
            'role_id'          => $this->role->id,
        ]);

        // keycloak_user_id should remain unchanged
        $user->refresh();
        expect($user->keycloak_user_id)->toBe('keycloak-user-109');
    });
});
