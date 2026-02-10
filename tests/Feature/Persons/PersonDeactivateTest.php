<?php

use App\Services\PersonKeycloakService;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Config;
use Webkul\Contact\Models\Person;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    $this->user = User::factory()->create(['first_name' => 'Test', 'last_name' => 'Admin']);
    $this->actingAs($this->user, 'user');
    $this->withoutMiddleware(Authenticate::class);
});

test('deactivating person with keycloak account does not cause SQL error from dirty state', function () {
    // Enable Keycloak so the observer's deletePortalAccount path is triggered
    Config::set('services.keycloak.client_id', 'test-client');

    // Mock the Keycloak service to avoid real API calls
    $keycloakService = Mockery::mock(PersonKeycloakService::class);
    $keycloakService->shouldReceive('delete')
        ->once()
        ->andReturn(['success' => true]);
    $this->app->instance(PersonKeycloakService::class, $keycloakService);

    /** @var Person $person */
    $person = Person::factory()->create([
        'first_name'       => 'Jan',
        'last_name'        => 'Jansen',
        'emails'           => [['value' => 'jan@example.com', 'label' => 'eigen', 'is_default' => true]],
        'is_active'        => true,
        'keycloak_user_id' => 'kc-existing-user',
        'user_id'          => $this->user->id,
    ]);

    // PUT request that changes is_active to false. The observer should
    // call deletePortalAccount which nullifies keycloak_user_id.
    // Before the fix, this caused a SQL error because the observer's
    // save() re-persisted all dirty fields (including non-existent columns).
    $response = $this->put(
        route('admin.contacts.persons.update', $person->id),
        [
            'first_name'  => 'Jan',
            'last_name'   => 'Jansen',
            'emails'      => [['value' => 'jan@example.com', 'label' => 'eigen', 'is_default' => true]],
            'phones'      => [],
            'is_active'   => 0,
            'entity_type' => 'persons',
        ]
    );

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $person->refresh();
    expect($person->is_active)->toBeFalsy()
        ->and($person->keycloak_user_id)->toBeNull();
});

test('deactivating person without keycloak still works', function () {
    Config::set('services.keycloak.client_id', 'test-client');

    $keycloakService = Mockery::mock(PersonKeycloakService::class);
    $keycloakService->shouldReceive('delete')
        ->once()
        ->andReturn(['success' => true]);
    $this->app->instance(PersonKeycloakService::class, $keycloakService);

    /** @var Person $person */
    $person = Person::factory()->create([
        'first_name' => 'Piet',
        'last_name'  => 'Ansen',
        'emails'     => [['value' => 'piet@example.com', 'label' => 'eigen', 'is_default' => true]],
        'is_active'  => true,
        'keycloak_user_id' => 'kc-existing-user',
        'user_id'    => $this->user->id,
    ]);
    $person->refresh();
    expect($person->keycloak_user_id)->toBe('kc-existing-user');

    $response = $this->put(
        route('admin.contacts.persons.update', $person->id),
        [
            'first_name'  => 'Piet',
            'last_name'   => 'Ansen',
            'emails'      => [['value' => 'piet@example.com', 'label' => 'eigen', 'is_default' => true]],
            'phones'      => [],
            'is_active'   => 0,
            'entity_type' => 'persons',
        ]
    );

    $response->assertRedirect();

    $person->refresh();
    expect($person->is_active)->toBeFalsy()
        ->and($person->keycloak_user_id)->toBeNull();
});
