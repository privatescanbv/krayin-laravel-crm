<?php

namespace Tests\Feature\Keycloak;

use App\Services\Keycloak\KeycloakConfigService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Keycloak\Helpers\KeycloakHttpHelpers;

beforeEach(function () {
    Http::preventStrayRequests();
    KeycloakHttpHelpers::setupConfig();
});

function removeHttpEventsListener(): ?string
{
    $service = app(KeycloakConfigService::class);

    return (fn () => $this->removeHttpEventsListener('token'))->call($service);
}

it('removes the http-events listener from the realm and keeps the others', function () {
    Http::fake([
        '*/events/config' => fn (Request $request) => $request->method() === 'GET'
            ? Http::response(['eventsEnabled' => true, 'eventsListeners' => ['jboss-logging', 'http-events'], 'enabledEventTypes' => []])
            : Http::response(null, 204),
    ]);

    expect(removeHttpEventsListener())->toBeNull();

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && $request['eventsListeners'] === ['jboss-logging']
        && $request['eventsEnabled'] === true);
});

it('leaves the realm alone when the listener is not registered', function () {
    Http::fake([
        '*/events/config' => Http::response(['eventsEnabled' => true, 'eventsListeners' => ['jboss-logging']]),
    ]);

    expect(removeHttpEventsListener())->toBeNull();

    Http::assertNotSent(fn (Request $request) => $request->method() === 'PUT');
});

function syncImpersonationAccess(string $clientId = 'clinic-app'): ?string
{
    $service = app(KeycloakConfigService::class);

    return (fn () => $this->syncImpersonationAccess($clientId, 'crm', 'token'))->call($service);
}

function fakeImpersonationRealm(bool $serviceAccountsEnabled, array $assignedRoles): void
{
    Http::fake([
        '*/clients?clientId=clinic-app'                         => Http::response([['id' => 'uuid-c', 'serviceAccountsEnabled' => $serviceAccountsEnabled]]),
        '*/clients?clientId=realm-management'                   => Http::response([['id' => 'uuid-rm']]),
        '*/clients/uuid-c/service-account-user'                 => Http::response(['id' => 'sa-1']),
        '*/clients/uuid-rm/roles/impersonation'                 => Http::response(['id' => 'role-1', 'name' => 'impersonation']),
        '*/users/sa-1/role-mappings/clients/uuid-rm'            => Http::response($assignedRoles),
        '*/clients/uuid-c'                                      => Http::response(null, 204),
    ]);
}

it('enables the service account and assigns the impersonation role', function () {
    fakeImpersonationRealm(serviceAccountsEnabled: false, assignedRoles: []);

    expect(syncImpersonationAccess())->toBeNull();

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/clients/uuid-c')
        && $request['serviceAccountsEnabled'] === true);
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/users/sa-1/role-mappings/clients/uuid-rm')
        && $request->data() === [['id' => 'role-1', 'name' => 'impersonation']]);
});

it('changes nothing when the impersonation role is already assigned', function () {
    fakeImpersonationRealm(serviceAccountsEnabled: true, assignedRoles: [['id' => 'role-1', 'name' => 'impersonation']]);

    expect(syncImpersonationAccess())->toBeNull();

    Http::assertNotSent(fn (Request $request) => in_array($request->method(), ['PUT', 'POST'], true));
});

it('reports an error when the impersonation role cannot be assigned', function () {
    Http::fake([
        '*/clients?clientId=clinic-app'              => Http::response([['id' => 'uuid-c', 'serviceAccountsEnabled' => true]]),
        '*/clients?clientId=realm-management'        => Http::response([['id' => 'uuid-rm']]),
        '*/clients/uuid-c/service-account-user'      => Http::response(['id' => 'sa-1']),
        '*/clients/uuid-rm/roles/impersonation'      => Http::response(['id' => 'role-1', 'name' => 'impersonation']),
        '*/users/sa-1/role-mappings/clients/uuid-rm' => fn (Request $request) => $request->method() === 'GET'
            ? Http::response([])
            : Http::response(['error' => 'forbidden'], 403),
    ]);

    expect(syncImpersonationAccess())->toContain('impersonation-role niet toekennen');
});
