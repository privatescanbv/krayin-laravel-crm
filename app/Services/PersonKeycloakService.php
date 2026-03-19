<?php

namespace App\Services;

use App\Actions\Keycloak\AddKeycloakUserAction;
use App\Actions\Keycloak\DeleteKeycloakUserAction;
use App\Actions\Keycloak\UpdateKeycloakUserAction;
use App\Enums\KeycloakRoles;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Webkul\Contact\Models\Person;

class PersonKeycloakService
{
    public function __construct(
        protected AddKeycloakUserAction $addKeycloakUserAction,
        protected UpdateKeycloakUserAction $updateKeycloakUserAction,
        protected DeleteKeycloakUserAction $deleteKeycloakUserAction,
    ) {}

    /**
     * Create a Keycloak account for the given person.
     *
     * @return array{success: bool, keycloak_user_id?: string, message?: string}
     */
    public function create(Person $person, ?string $password = null, bool $temporary = false): array
    {
        $email = $this->getPrimaryEmail($person);

        if (! $email) {
            return [
                'success' => false,
                'message' => 'Persoon heeft geen (primaire) e-mail adres voor Keycloak.',
            ];
        }

        $passwordToUse = $password ?? $person->getPlaintextPassword() ?? $person->getDecryptedPassword();

        if (! $passwordToUse) {
            $passwordToUse = Str::random(16);

            Person::withoutEvents(function () use ($person, $passwordToUse) {
                $person->forceFill(['password' => $passwordToUse])->save();
            });
        }

        $userData = $this->buildUserPayload($person, $email);

        $result = $this->addKeycloakUserAction->execute(
            $userData,
            $passwordToUse,
            $temporary,
            KeycloakRoles::Patient->value
        );

        if ($result['success']) {
            $result['generated_password'] = $passwordToUse;
        }

        return $result;
    }

    /**
     * Update the Keycloak account for the given person.
     *
     * @param  array<int, string>  $changedFields
     * @return array{success: bool, message?: string}
     */
    public function update(Person $person, array $changedFields, ?string $password = null, bool $temporary = false): array
    {
        if (empty($person->keycloak_user_id)) {
            return [
                'success' => false,
                'message' => 'Persoon is niet gekoppeld aan een Keycloak gebruiker.',
            ];
        }

        Log::info('Updating portal account for person', [
            'person_id' => $person->id,
            'fields'    => $changedFields,
        ]);

        $userData = [];

        if (in_array('emails', $changedFields, true)) {
            $email = $this->getPrimaryEmail($person);
            if ($email) {
//                $userData['email'] = $email;
//                $userData['username'] = $email;
//                TODO https://trello.com/c/85uqm50P/199-crm-patientportaal-e-mail-wijzigen?filter=member%3Amarkbulthuis
                Log::warning('Ignore changing email or username, keycloak does not accept is this way.');
            }
        }

        if ($this->nameFieldsChanged($changedFields)) {
            $userData['firstName'] = $person->first_name ?? '';
            $userData['lastName'] = $this->buildLastName($person);
        }

        if (in_array('is_active', $changedFields, true)) {
            $userData['enabled'] = (bool) $person->is_active;
        }

        $password = $password ?? $person->getPlaintextPassword();
        if ($password === null && in_array('password', $changedFields, true)) {
            $password = $person->getDecryptedPassword();
        }

        if (empty($userData) && $password === null) {
            return ['success' => true];
        }

        Log::info('Updating keycloak account for person', ['person_id' => $person->id, 'keycloak_user_id' => $person->keycloak_user_id]);
        return $this->updateKeycloakUserAction->execute(
            $person->keycloak_user_id,
            $userData,
            $password,
            $temporary
        );
    }

    /**
     * Remove the Keycloak account for the given person.
     *
     * @return array{success: bool, message?: string}
     */
    public function delete(Person $person): array
    {
        if (empty($person->keycloak_user_id)) {
            return [
                'success' => true,
                'message' => 'Persoon had geen gekoppelde Keycloak gebruiker.',
            ];
        }

        return $this->deleteKeycloakUserAction->execute($person->keycloak_user_id);
    }

    /**
     * Build standard Keycloak payload for a person.
     */
    protected function buildUserPayload(Person $person, string $email): array
    {
        return [
            'username'      => $email,
            'email'         => $email,
            'firstName'     => $person->first_name ?? '',
            'lastName'      => $this->buildLastName($person),
            'enabled'       => (bool) $person->is_active,
            'emailVerified' => true,
            'attributes'    => [
                'person_id' => [(string) $person->id],
                'unique_id' => [(string) $person->id],
            ],
        ];
    }

    /**
     * Compose last name including prefix/married name.
     */
    protected function buildLastName(Person $person): string
    {
        $parts = [];

        if ($person->lastname_prefix) {
            $parts[] = trim($person->lastname_prefix);
        }

        if ($person->last_name) {
            $parts[] = trim($person->last_name);
        }

        if ($person->married_name) {
            $parts[] = trim($person->married_name);
        }

        return trim(implode(' ', array_filter($parts)));
    }

    /**
     * Determine if any name-related fields changed.
     *
     * @param  array<int, string>  $changedFields
     */
    protected function nameFieldsChanged(array $changedFields): bool
    {
        $nameFields = ['first_name', 'last_name', 'lastname_prefix', 'married_name', 'married_name_prefix'];

        return (bool) array_intersect($nameFields, $changedFields);
    }

    /**
     * Helper to grab the default email from the person record.
     */
    protected function getPrimaryEmail(Person $person): ?string
    {
        if (method_exists($person, 'findDefaultEmail')) {
            return $person->findDefaultEmail();
        }

        // Fallback to first email entry if available
        if (! empty($person->emails) && is_array($person->emails)) {
            return $person->emails[0]['value'] ?? null;
        }

        return null;
    }
}
