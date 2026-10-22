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
