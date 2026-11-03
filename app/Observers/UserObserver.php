<?php

namespace App\Observers;

use App\Actions\Keycloak\AddKeycloakUserAction;
use App\Actions\Keycloak\DeleteKeycloakUserAction;
use App\Actions\Keycloak\UpdateKeycloakUserAction;
use App\Enums\KeycloakRoles;
use App\Services\Keycloak\KeycloakService;
use App\Support\PasswordGenerator;
use Database\Seeders\UserSeeder;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

/**
 * UserObserver - Automatically synchronizes CRM users with Keycloak.
 *
 * This observer handles:
 * - Creating users in Keycloak when a user is created in CRM (if status = 1)
 * - Updating users in Keycloak when email, first_name, last_name, password, or status changes
 * - Deleting users from Keycloak when a user is deleted in CRM
 * - Deleting users from Keycloak when status changes to inactive (0)
 * - Creating users in Keycloak when status changes to active (1)
 *
 * Requirements:
 * - Keycloak must be configured (services.keycloak.client_id must be set)
 * - User must have an email address
 * - For password updates, the plaintext password is captured before hashing
 */
class UserObserver
{
    /**
     * Store plaintext password temporarily before it gets hashed.
     */
    protected static array $plaintextPasswords = [];

    public function __construct(
        protected AddKeycloakUserAction $addKeycloakUserAction,
        protected UpdateKeycloakUserAction $updateKeycloakUserAction,
        protected DeleteKeycloakUserAction $deleteKeycloakUserAction,
        protected KeycloakService $keycloakService
    ) {}

    /**
     * Keycloak realm role for a CRM role: clinic for the portal roles (RoleCode), otherwise employee.
     */
    public static function keycloakRoleFor(?int $roleId): string
    {
        return (($roleId ? Role::find($roleId)?->code : null)?->keycloakRole() ?? KeycloakRoles::Employee)->value;
    }

    /**
     * Handle the User "saving" event.
     * Capture plaintext password before it gets hashed.
     */
    public function saving(User $user): void
    {
        // Only capture if Keycloak is configured
        if (! $this->isKeycloakConfigured()) {
            return;
        }

        if (! $user->isDirty('password')) {
            return;
        }

        $key = $user->id ?? 'new';

        // Try to get plaintext password from the model's temporary attribute
        // This is set by the User model's setPasswordAttribute mutator
        if (method_exists($user, 'getPlaintextPassword')) {
            $plaintextPassword = $user->getPlaintextPassword();
            if (! empty($plaintextPassword)) {
                // Store plaintext password temporarily
                self::$plaintextPasswords[$key] = $plaintextPassword;

                return;
            }
        }

        // Fallback: Capture plaintext password if it's being set and not already hashed
        // This handles cases where the mutator might not be used
        if (! empty($user->password)) {
            $password = $user->password;

            // Check if password is already hashed (Laravel bcrypt hashes start with $2y$)
            if (! str_starts_with($password, '$2y$') && ! str_starts_with($password, '$2a$') && ! str_starts_with($password, '$2b$')) {
                // Store plaintext password temporarily
                if (! isset(self::$plaintextPasswords[$key])) {
                    self::$plaintextPasswords[$key] = $password;
                }

                return;
            }
        }

        // Last resort: Try to get password from UserSeeder (for seeded users)
        if (! empty($user->email) && ! isset(self::$plaintextPasswords[$key])) {
            $seederPassword = UserSeeder::getPasswordForEmail($user->email);
            if (! empty($seederPassword)) {
                self::$plaintextPasswords[$key] = $seederPassword;
            }
        }
    }

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // Only sync if Keycloak is configured
        if (! $this->isKeycloakConfigured()) {
            return;
        }

        // Skip if user has no email
        if (empty($user->email)) {
            return;
        }

        // Skip if user is not active (status = 0)
        if ($user->status != 1) {
            return;
        }

        // Skip if user already has keycloak_user_id (already synced)
        if (! empty($user->keycloak_user_id)) {
            return;
        }

        $this->syncUserToKeycloak($user, 'created');
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // Only sync if Keycloak is configured
        if (! $this->isKeycloakConfigured()) {
            return;
        }

        // Skip if user has no email
        if (empty($user->email)) {
            return;
        }

        // Check if relevant fields changed
        $relevantFields = ['email', 'first_name', 'last_name', 'status', 'password', 'role_id'];
        $changedFields = array_intersect($relevantFields, array_keys($user->getDirty()));

        if (empty($changedFields)) {
            return;
        }

        // If user has keycloak_user_id, update in Keycloak
        if (! empty($user->keycloak_user_id)) {
            // If status changed to inactive (0), delete from Keycloak
            if ($user->wasChanged('status') && $user->status == 0) {
                $this->deleteUserFromKeycloak($user);

                return;
            }

            // If status changed to active (1), user should already exist, but ensure it's enabled
            if ($user->wasChanged('status') && $user->status == 1) {
                $this->updateUserInKeycloak($user, $changedFields);

                return;
            }

            if (in_array('role_id', $changedFields, true)) {
                $this->syncRoleChange($user);
                $changedFields = array_values(array_diff($changedFields, ['role_id']));

                if (empty($changedFields)) {
                    return;
                }
            }

            // Update user data in Keycloak
            $this->updateUserInKeycloak($user, $changedFields);
        } else {
            // User doesn't have keycloak_user_id yet (never synced, e.g. Keycloak was down on create):
            // create it on any relevant change of an active user, so re-saving (with a password) repairs it
            if ($user->status == 1) {
                $this->syncUserToKeycloak($user, $user->wasChanged('status') ? 'activated' : 'resync');
            }
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        // Only sync if Keycloak is configured
        if (! $this->isKeycloakConfigured()) {
            return;
        }

        // Only delete if user has keycloak_user_id
        if (empty($user->keycloak_user_id)) {
            return;
        }

        // Delete from Keycloak and clear keycloak_user_id since user is deleted from CRM
        $keycloakUserId = $user->keycloak_user_id;
        $result = $this->deleteKeycloakUserAction->execute($keycloakUserId);

        if ($result['success']) {
            Log::info('User deleted from Keycloak via observer (user deleted from CRM)', [
                'user_id'     => $user->id,
                'email'       => $user->email,
                'keycloak_id' => $keycloakUserId,
            ]);
        } else {
            Log::warning('Failed to delete user from Keycloak via observer (user deleted from CRM)', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'message' => $result['message'] ?? 'Unknown error',
            ]);
        }
    }

    /**
     * Persist keycloak_user_id without firing model events. A nested update() inside a running save
     * re-enters updated() with the password still dirty, while its plaintext is already consumed,
     * which made the observer overwrite the Keycloak password with a random one.
     */
    protected function storeKeycloakUserId(User $user, ?string $keycloakUserId): void
    {
        $user->newQuery()->whereKey($user->getKey())->update(['keycloak_user_id' => $keycloakUserId]);
        $user->keycloak_user_id = $keycloakUserId;
        $user->syncOriginalAttribute('keycloak_user_id');
    }

    /**
     * Check if Keycloak is configured.
     */
    protected function isKeycloakConfigured(): bool
    {
        return ! empty(Config::get('services.keycloak.client_id'));
    }

    /**
     * Sync user to Keycloak (create).
     */
    protected function syncUserToKeycloak(User $user, string $reason = 'created'): void
    {
        try {
            $userData = [
                'username'      => $user->email,
                'email'         => $user->email,
                'firstName'     => $user->first_name ?? '',
                'lastName'      => $user->last_name ?? '',
                'enabled'       => $user->status == 1,
                'emailVerified' => true,
            ];

            // Get password - try plaintext from temporary storage first
            // saving() stores a new user's password under 'new' (no id yet); created() runs with the id
            $passwordKey = isset(self::$plaintextPasswords[$user->id]) ? $user->id : 'new';
            $password = self::$plaintextPasswords[$passwordKey] ?? null;

            // If not found, try to get from UserSeeder (for seeded users)
            if ($password === null && ! empty($user->email)) {
                $password = UserSeeder::getPasswordForEmail($user->email);
            }

            // If still not found, try other methods
            if ($password === null) {
                $password = $this->getPasswordForUser($user);
            } else {
                // Remove from temporary storage after use
                unset(self::$plaintextPasswords[$passwordKey]);
            }

            $temporary = empty($user->password) && empty($password); // Temporary if no password was set

            $result = $this->addKeycloakUserAction->execute($userData, $password, $temporary, self::keycloakRoleFor($user->role_id));

            if ($result['success']) {
                // Update keycloak_user_id in CRM
                $this->storeKeycloakUserId($user, $result['keycloak_user_id']);

                Log::info('User synced to Keycloak via observer', [
                    'user_id'     => $user->id,
                    'email'       => $user->email,
                    'keycloak_id' => $result['keycloak_user_id'],
                    'reason'      => $reason,
                ]);
            } else {
                Log::warning('Failed to sync user to Keycloak via observer', [
                    'user_id' => $user->id,
                    'email'   => $user->email,
                    'reason'  => $reason,
                    'message' => $result['message'] ?? 'Unknown error',
                ]);
            }
        } catch (Exception $e) {
            Log::error('Exception while syncing user to Keycloak via observer', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'reason'  => $reason,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update user in Keycloak.
     */
    protected function updateUserInKeycloak(User $user, array $changedFields): void
    {
        try {
            $userData = [];

            // Only include changed fields
            if (in_array('email', $changedFields)) {
                $userData['email'] = $user->email;
                $userData['username'] = $user->email;
            }

            if (in_array('first_name', $changedFields)) {
                $userData['firstName'] = $user->first_name ?? '';
            }

            if (in_array('last_name', $changedFields)) {
                $userData['lastName'] = $user->last_name ?? '';
            }

            if (in_array('status', $changedFields)) {
                $userData['enabled'] = $user->status == 1;
            }

            // Get password if it changed
            $password = null;
            if (in_array('password', $changedFields)) {
                // Try to get plaintext password from temporary storage
                $password = self::$plaintextPasswords[$user->id] ?? null;

                // If not found, try to get from UserSeeder first (for seeded users)
                if ($password === null && ! empty($user->email)) {
                    $password = UserSeeder::getPasswordForEmail($user->email);
                }

                // If still not found, try other methods
                if ($password === null) {
                    $password = $this->getPasswordForUser($user);
                } else {
                    // Remove from temporary storage after use
                    unset(self::$plaintextPasswords[$user->id]);
                }
            }

            if (empty($userData) && $password === null) {
                return; // Nothing to update
            }

            $result = $this->updateKeycloakUserAction->execute($user->keycloak_user_id, $userData, $password, false);

            if ($result['success']) {
                Log::info('User updated in Keycloak via observer', [
                    'user_id'        => $user->id,
                    'email'          => $user->email,
                    'keycloak_id'    => $user->keycloak_user_id,
                    'changed_fields' => $changedFields,
                ]);
            } else {
                Log::warning('Failed to update user in Keycloak via observer', [
                    'user_id' => $user->id,
                    'email'   => $user->email,
                    'message' => $result['message'] ?? 'Unknown error',
                ]);
            }
        } catch (Exception $e) {
            Log::error('Exception while updating user in Keycloak via observer', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete user from Keycloak.
     */
    protected function deleteUserFromKeycloak(User $user): void
    {
        try {
            $keycloakUserId = $user->keycloak_user_id;
            $result = $this->deleteKeycloakUserAction->execute($keycloakUserId);
            // always remote the relation, even when user could not be found in Keycloak, to prevent orphaned keycloak_user_id references
            $this->storeKeycloakUserId($user, null);
            if ($result['success']) {
                Log::info('User deleted from Keycloak via observer', [
                    'user_id'     => $user->id,
                    'email'       => $user->email,
                    'keycloak_id' => $keycloakUserId,
                ]);
            } else {
                Log::warning('Failed to delete user from Keycloak via observer', [
                    'user_id' => $user->id,
                    'email'   => $user->email,
                    'message' => $result['message'] ?? 'Unknown error',
                ]);
            }
        } catch (Exception $e) {
            Log::error('Exception while deleting user from Keycloak via observer', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get password for user.
     * Tries to get from user attributes, falls back to UserSeeder, default or generates temporary password.
     */
    protected function getPasswordForUser(User $user): string
    {
        // Try to get password from UserSeeder (for seeded users)
        if (! empty($user->email)) {
            $seederPassword = UserSeeder::getPasswordForEmail($user->email);
            if (! empty($seederPassword)) {
                return $seederPassword;
            }
        }

        // Try to get password from user attributes (if available)
        if (! empty($user->getAttributes()['password'])) {
            // Password is hashed, but we need plain text for Keycloak
            // Since we can't decrypt, we'll use a default or generate one
        }

        // Try to get default password from config
        $defaultPassword = Config::get('services.keycloak.default_password');

        if (! empty($defaultPassword)) {
            return $defaultPassword;
        }

        // Generate a temporary random password compliant with the application password policy
        return PasswordGenerator::generate();
    }

    /**
     * Swap the Keycloak realm role when the CRM role changes.
     */
    protected function syncRoleChange(User $user): void
    {
        $oldRole = self::keycloakRoleFor($user->getRawOriginal('role_id'));
        $newRole = self::keycloakRoleFor($user->role_id);

        if ($oldRole === $newRole) {
            return;
        }

        try {
            $token = $this->keycloakService->getAdminToken();

            $this->keycloakService->assignRoleToUser($user->keycloak_user_id, $newRole, $token);
            $this->keycloakService->removeRoleFromUser($user->keycloak_user_id, $oldRole, $token);
        } catch (Exception $e) {
            Log::error('Exception while syncing role change to Keycloak via observer', [
                'user_id'  => $user->id,
                'old_role' => $oldRole,
                'new_role' => $newRole,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
