<?php

namespace Tests\Feature\Keycloak;

use App\Services\PersonKeycloakService;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Keycloak\Helpers\KeycloakHttpHelpers;
use Webkul\Contact\Models\Person;

beforeEach(function () {
    Http::preventStrayRequests();
    KeycloakHttpHelpers::setupConfig();
});

it('reuses the cached Keycloak lookup across calls for the same user', function () {
    KeycloakHttpHelpers::fakeAdminToken();
    KeycloakHttpHelpers::fakeUserOperations([
        'user_by_id' => [
            'kc-user-cache' => [
                'id'    => 'kc-user-cache',
                'email' => 'cached@example.com',
            ],
        ],
    ]);

    $person = Person::factory()->create(['keycloak_user_id' => 'kc-user-cache']);
    $service = app(PersonKeycloakService::class);

    expect($service->getAccountEmail($person))->toBe('cached@example.com');
    expect($service->getAccountEmail($person))->toBe('cached@example.com');

    Http::assertSentCount(2); // 1 admin token + 1 user lookup, not 2 lookups
});
