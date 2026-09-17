<?php

namespace Tests\Feature\Keycloak;

use App\Actions\Keycloak\GetKeycloakActiveSessionCountAction;
use App\Enums\KeyCloakClient;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Keycloak\Helpers\KeycloakHttpHelpers;

beforeEach(function () {
    Http::preventStrayRequests();
    KeycloakHttpHelpers::setupConfig();
});

describe('GetKeycloakActiveSessionCountAction', function () {
    it('returns the active session count for the patient portal client', function () {
        KeycloakHttpHelpers::fakeAdminToken();
        KeycloakHttpHelpers::fakeClientSessionCount('patient-app', 'crm', 7);

        $action = app(GetKeycloakActiveSessionCountAction::class);
        $result = $action->execute(KeyCloakClient::PATIENT);

        expect($result['success'])->toBeTrue();
        expect($result['count'])->toBe(7);
    });

    it('fails when the admin token cannot be obtained', function () {
        KeycloakHttpHelpers::fakeAdminToken('', 401);

        $action = app(GetKeycloakActiveSessionCountAction::class);
        $result = $action->execute(KeyCloakClient::PATIENT);

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toContain('authenticeren');
    });

    it('fails when the client cannot be found in Keycloak', function () {
        KeycloakHttpHelpers::fakeAdminToken();
        KeycloakHttpHelpers::fakeClientSessionCount('patient-app', 'crm', null);

        $action = app(GetKeycloakActiveSessionCountAction::class);
        $result = $action->execute(KeyCloakClient::PATIENT);

        expect($result['success'])->toBeFalse();
    });
});
