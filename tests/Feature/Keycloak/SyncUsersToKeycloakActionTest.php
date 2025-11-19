<?php

namespace Tests\Feature\Keycloak;

use App\Actions\Keycloak\AddKeycloakUserAction;
use App\Actions\Keycloak\SyncUsersToKeycloakAction;
use App\Services\KeycloakService;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Keycloak\Helpers\KeycloakHttpHelpers;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;
use Webkul\User\Repositories\UserRepository;

beforeEach(function () {
    Http::preventStrayRequests();
    KeycloakHttpHelpers::setupConfig([
        'default_role_id' => 1,
    ]);

    $this->seed(TestSeeder::class);
    $this->role = Role::query()->first() ?? Role::factory()->create();
});

/**
 * Create a test-specific SyncUsersToKeycloakAction with test passwords.
 */
function createTestAction(array $passwords): SyncUsersToKeycloakAction
{
    return new class(app(KeycloakService::class), app(UserRepository::class), app(AddKeycloakUserAction::class), $passwords) extends SyncUsersToKeycloakAction
    {
        public function __construct(
            protected KeycloakService $keycloakService,
            protected UserRepository $userRepository,
            protected AddKeycloakUserAction $addKeycloakUserAction,
            protected array $testPasswords = []
        ) {
            parent::__construct($keycloakService, $userRepository, $addKeycloakUserAction);
        }

        protected function getPasswordFromSeeder(string $email): ?string
        {
            return $this->testPasswords[$email] ?? parent::getPasswordFromSeeder($email);
        }
    };
}

it('can sync users to Keycloak', function () {
    User::query()->where('id', '!=', 0)->update(['status' => 0]);
    KeycloakHttpHelpers::isolateTest();

    $user1 = User::factory()->create([
        'email'            => 'user1@example.com',
        'first_name'       => 'User',
        'last_name'        => 'One',
        'status'           => 1,
        'role_id'          => $this->role->id,
        'keycloak_user_id' => null,
        'password'         => bcrypt('test-password-1'),
    ]);

    $user2 = User::factory()->create([
        'email'            => 'user2@example.com',
        'first_name'       => 'User',
        'last_name'        => 'Two',
        'status'           => 1,
        'role_id'          => $this->role->id,
        'keycloak_user_id' => null,
        'password'         => bcrypt('test-password-2'),
    ]);

    KeycloakHttpHelpers::enableKeycloak();
    KeycloakHttpHelpers::fakeAdminToken();
    KeycloakHttpHelpers::fakeUserOperations([
        'email_checks' => [
            'user1@example.com' => null,
            'user2@example.com' => null,
        ],
        'create_responses' => ['keycloak-user-1', 'keycloak-user-2'],
    ]);

    $action = createTestAction([
        'user1@example.com' => 'test-password-1',
        'user2@example.com' => 'test-password-2',
    ]);

    $result = $action->execute(false);

    expect($result['success'])->toBeTrue()
        ->and($result['synced'])->toBe(2)
        ->and($result['errors'])->toBe(0);

    $user1->refresh();
    $user2->refresh();
    expect($user1->keycloak_user_id)->toBe('keycloak-user-1')
        ->and($user2->keycloak_user_id)->toBe('keycloak-user-2');
});

it('skips users that already exist in Keycloak', function () {
    User::query()->where('id', '!=', 0)->update(['status' => 0]);
    KeycloakHttpHelpers::isolateTest();

    $user = User::factory()->create([
        'email'            => 'existing@example.com',
        'status'           => 1,
        'role_id'          => $this->role->id,
        'keycloak_user_id' => null,
    ]);

    KeycloakHttpHelpers::enableKeycloak();
    KeycloakHttpHelpers::fakeAdminToken();
    KeycloakHttpHelpers::fakeUserOperations([
        'email_checks' => [
            'existing@example.com' => [
                'id'    => 'existing-keycloak-id',
                'email' => 'existing@example.com',
            ],
        ],
    ]);

    $result = app(SyncUsersToKeycloakAction::class)->execute(false);

    expect($result['success'])->toBeTrue()
        ->and($result['synced'])->toBe(1)
        ->and($result['skipped'])->toBe(0);

    $user->refresh();
    expect($user->keycloak_user_id)->toBe('existing-keycloak-id');
});

it('skips users without email', function () {
    User::query()->where('id', '!=', 0)->update(['status' => 0]);
    KeycloakHttpHelpers::isolateTest();

    User::factory()->create([
        'email'   => '',
        'status'  => 1,
        'role_id' => $this->role->id,
    ]);

    KeycloakHttpHelpers::enableKeycloak();
    KeycloakHttpHelpers::fakeAdminToken();

    $result = app(SyncUsersToKeycloakAction::class)->execute(false);

    expect($result['success'])->toBeTrue()
        ->and($result['synced'])->toBe(0)
        ->and($result['skipped'])->toBe(1);
});

it('handles errors when creating user fails', function () {
    User::query()->where('id', '!=', 0)->update(['status' => 0]);
    KeycloakHttpHelpers::isolateTest();

    User::factory()->create([
        'email'   => 'error-user@example.com',
        'status'  => 1,
        'role_id' => $this->role->id,
    ]);

    KeycloakHttpHelpers::enableKeycloak();
    KeycloakHttpHelpers::fakeAdminToken();
    Http::fake([
        'host.docker.internal:8085/admin/realms/crm/users*' => function ($request) {
            if ($request->method() === 'GET') {
                $parsedUrl = parse_url($request->url());
                if (isset($parsedUrl['query'])) {
                    parse_str($parsedUrl['query'], $queryParams);
                    if (isset($queryParams['email']) && $queryParams['email'] === 'error-user@example.com') {
                        return Http::response([], 200);
                    }
                }
            }
            if ($request->method() === 'POST') {
                return Http::response(['error' => 'User creation failed'], 400);
            }

            return Http::response('', 404);
        },
    ]);

    $action = createTestAction(['error-user@example.com' => 'test-password-error']);
    $result = $action->execute(false);

    expect($result['success'])->toBeTrue()
        ->and($result['synced'])->toBe(0)
        ->and($result['errors'])->toBe(1);
});

it('can perform dry run', function () {
    User::query()->where('id', '!=', 0)->update(['status' => 0]);
    KeycloakHttpHelpers::isolateTest();

    $user = User::factory()->create([
        'email'   => 'dryrun-user@example.com',
        'status'  => 1,
        'role_id' => $this->role->id,
    ]);

    KeycloakHttpHelpers::enableKeycloak();
    KeycloakHttpHelpers::fakeAdminToken();
    KeycloakHttpHelpers::fakeUserOperations([
        'email_checks' => ['dryrun-user@example.com' => null],
    ]);

    $action = createTestAction(['dryrun-user@example.com' => 'test-password-dryrun']);
    $result = $action->execute(true);

    expect($result['success'])->toBeTrue()
        ->and($result['synced'])->toBe(1)
        ->and($result['errors'])->toBe(0);

    $user->refresh();
    expect($user->keycloak_user_id)->toBeNull();
});

it('fails when admin token cannot be obtained', function () {
    KeycloakHttpHelpers::fakeAdminToken('', 401);

    $action = app(SyncUsersToKeycloakAction::class);
    $result = $action->execute(false);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('authenticeren');
});
