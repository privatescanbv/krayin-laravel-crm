<?php

namespace Tests\Feature\Keycloak;

use App\Actions\Keycloak\AddKeycloakUserAction;
use App\Actions\Keycloak\DeleteKeycloakUserAction;
use App\Actions\Keycloak\SetKeycloakUserPasswordAction;
use App\Actions\Keycloak\UpdateKeycloakUserAction;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Keycloak\Helpers\KeycloakHttpHelpers;

beforeEach(function () {
    Http::preventStrayRequests();
    KeycloakHttpHelpers::setupConfig();
});

describe('AddKeycloakUserAction', function () {
    it('can add user with password', function () {
        KeycloakHttpHelpers::fakeAdminToken();
        KeycloakHttpHelpers::fakeUserOperations([
            'email_checks'     => ['newuser@example.com' => null],
            'create_responses' => ['new-user-123'],
        ]);

        $action = app(AddKeycloakUserAction::class);
        $result = $action->execute([
            'username'      => 'newuser@example.com',
            'email'         => 'newuser@example.com',
            'firstName'     => 'New',
            'lastName'      => 'User',
            'enabled'       => true,
            'emailVerified' => true,
        ], 'password123', false);

        expect($result['success'])->toBeTrue();
        expect($result['keycloak_user_id'])->toBe('new-user-123');
    });

    it('fails when user already exists', function () {
        KeycloakHttpHelpers::fakeAdminToken();
        KeycloakHttpHelpers::fakeUserOperations([
            'email_checks' => [
                'existing-action@example.com' => [
                    'id'    => 'existing-user-123',
                    'email' => 'existing-action@example.com',
                ],
            ],
        ]);

        $action = app(AddKeycloakUserAction::class);
        $result = $action->execute([
            'email' => 'existing-action@example.com',
        ], 'password123', false);

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toContain('bestaat al');
    });

    it('fails when admin token cannot be obtained', function () {
        KeycloakHttpHelpers::fakeAdminToken('', 401);

        $action = app(AddKeycloakUserAction::class);
        $result = $action->execute([
            'email' => 'newuser@example.com',
        ], 'password123', false);

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toContain('authenticeren');
    });
});

describe('UpdateKeycloakUserAction', function () {
    it('can update user with password', function () {
        KeycloakHttpHelpers::fakeAdminToken();
        KeycloakHttpHelpers::fakeUserOperations([
            'user_by_id' => [
                'user-123' => [
                    'id'    => 'user-123',
                    'email' => 'test@example.com',
                ],
            ],
        ]);

        $action = app(UpdateKeycloakUserAction::class);
        $result = $action->execute('user-123', [
            'firstName' => 'Updated',
            'lastName'  => 'Name',
        ], 'newpassword123', false);

        expect($result['success'])->toBeTrue();
    });

    it('can update user without password', function () {
        KeycloakHttpHelpers::fakeAdminToken();
        KeycloakHttpHelpers::fakeUserOperations([
            'user_by_id' => [
                'user-123' => [
                    'id'    => 'user-123',
                    'email' => 'test@example.com',
                ],
            ],
        ]);

        $action = app(UpdateKeycloakUserAction::class);
        $result = $action->execute('user-123', [
            'firstName' => 'Updated',
        ], null);

        expect($result['success'])->toBeTrue();
    });

    it('fails when user does not exist', function () {
        KeycloakHttpHelpers::fakeAdminToken();
        // No user_by_id config means user lookup will return 404

        $action = app(UpdateKeycloakUserAction::class);
        $result = $action->execute('nonexistent', [
            'firstName' => 'Updated',
        ], null);

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toContain('niet gevonden');
    });
});

describe('DeleteKeycloakUserAction', function () {
    it('can delete user', function () {
        KeycloakHttpHelpers::fakeAdminToken();
        KeycloakHttpHelpers::fakeUserOperations([
            'user_by_id' => [
                'user-123' => [
                    'id'    => 'user-123',
                    'email' => 'test@example.com',
                ],
            ],
        ]);

        $action = app(DeleteKeycloakUserAction::class);
        $result = $action->execute('user-123');

        expect($result['success'])->toBeTrue();
    });

    it('fails when user does not exist', function () {
        KeycloakHttpHelpers::fakeAdminToken();
        // No user_by_id config means user lookup will return 404

        $action = app(DeleteKeycloakUserAction::class);
        $result = $action->execute('nonexistent');

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toContain('niet gevonden');
    });
});

describe('SetKeycloakUserPasswordAction', function () {
    it('can set password for user', function () {
        KeycloakHttpHelpers::fakeAdminToken();
        KeycloakHttpHelpers::fakeUserOperations([
            'email_checks' => [
                'test@example.com' => [
                    'id'    => 'user-123',
                    'email' => 'test@example.com',
                ],
            ],
        ]);

        $action = app(SetKeycloakUserPasswordAction::class);
        $result = $action->execute('test@example.com', 'newpassword123', false);

        expect($result['success'])->toBeTrue();
        expect($result['keycloak_user_id'])->toBe('user-123');
    });

    it('can set temporary password', function () {
        KeycloakHttpHelpers::fakeAdminToken();
        KeycloakHttpHelpers::fakeUserOperations([
            'email_checks' => [
                'test@example.com' => [
                    'id'    => 'user-123',
                    'email' => 'test@example.com',
                ],
            ],
        ]);

        $action = app(SetKeycloakUserPasswordAction::class);
        $result = $action->execute('test@example.com', 'temppassword', true);

        expect($result['success'])->toBeTrue();
    });

    it('fails when user does not exist', function () {
        KeycloakHttpHelpers::fakeAdminToken();
        KeycloakHttpHelpers::fakeUserOperations([
            'email_checks' => ['notfound@example.com' => null], // Empty array means user not found
        ]);

        $action = app(SetKeycloakUserPasswordAction::class);
        $result = $action->execute('notfound@example.com', 'password123', false);

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toContain('niet gevonden');
    });
});
