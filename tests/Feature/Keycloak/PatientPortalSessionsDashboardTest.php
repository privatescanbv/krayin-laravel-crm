<?php

namespace Tests\Feature\Keycloak;

use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Feature\Keycloak\Helpers\KeycloakHttpHelpers;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    Http::preventStrayRequests();
    KeycloakHttpHelpers::setupConfig();
    Cache::flush();
    $this->actingAs(getDefaultAdmin(), 'user');
});

it('returns the active session count when Keycloak is reachable', function () {
    KeycloakHttpHelpers::fakeAdminToken();
    KeycloakHttpHelpers::fakeClientSessionCount('patient-app', 'crm', 3);

    $response = $this->getJson(route('admin.dashboard.patient-portal-sessions'));

    $response->assertOk();
    $response->assertJson([
        'available' => true,
        'count'     => 3,
    ]);
    expect($response->json('fetched_at'))->not->toBeNull();
});

it('reports unavailable without a fake zero count when Keycloak cannot be reached', function () {
    KeycloakHttpHelpers::fakeAdminToken('', 401);

    $response = $this->getJson(route('admin.dashboard.patient-portal-sessions'));

    $response->assertOk();
    $response->assertJson([
        'available' => false,
        'count'     => null,
    ]);
});

it('keeps showing the last successful fetch time while Keycloak is down', function () {
    // Http::fake() matches the first-registered fake for a URL, so simulate the
    // token endpoint going down on its second call via a response sequence.
    $internalUrlPattern = str_replace(['http://', 'https://'], '', config('services.keycloak.base_url_internal'));

    Http::fake([
        $internalUrlPattern.'/realms/master/protocol/openid-connect/token' => Http::sequence()
            ->push(['access_token' => 'test-access-token'], 200)
            ->push(['error' => 'invalid_grant'], 401),
    ]);
    KeycloakHttpHelpers::fakeClientSessionCount('patient-app', 'crm', 5);

    $firstResponse = $this->getJson(route('admin.dashboard.patient-portal-sessions'));
    $firstResponse->assertJson(['available' => true, 'count' => 5]);
    $lastGoodFetchedAt = $firstResponse->json('fetched_at');

    Cache::forget('dashboard.patient-portal-sessions.attempt');

    $secondResponse = $this->getJson(route('admin.dashboard.patient-portal-sessions'));

    $secondResponse->assertOk();
    $secondResponse->assertJson([
        'available'  => false,
        'count'      => null,
        'fetched_at' => $lastGoodFetchedAt,
    ]);
});

it('caches the Keycloak attempt so repeated requests do not hit Keycloak again', function () {
    KeycloakHttpHelpers::fakeAdminToken();
    KeycloakHttpHelpers::fakeClientSessionCount('patient-app', 'crm', 2);

    $this->getJson(route('admin.dashboard.patient-portal-sessions'))->assertOk();

    Http::fake(function () {
        throw new RuntimeException('Keycloak should not be called again within the cache TTL.');
    });

    $response = $this->getJson(route('admin.dashboard.patient-portal-sessions'));

    $response->assertOk();
    $response->assertJson(['available' => true, 'count' => 2]);
});
