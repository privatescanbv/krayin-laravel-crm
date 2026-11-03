<?php

use App\Enums\RoleCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123']]);
    // Keycloak admin API, called by UserObserver when the user changes.
    Http::fake(['*' => Http::response(['access_token' => 'test-admin-token'], 200)]);
});

function profileUser(array $attributes = [], ?RoleCode $roleCode = RoleCode::KLINIEK_MEDEWERKER): User
{
    $role = Role::factory()->create(['code' => $roleCode?->value]);

    return User::withoutEvents(fn () => User::factory()->create([
        'first_name'       => 'Piet',
        'last_name'        => 'Jansen',
        'email'            => 'piet@kliniek.example',
        'status'           => 1,
        'keycloak_user_id' => 'kc-clinic-1',
        'role_id'          => $role->id,
        'password'         => Hash::make('OudWachtwoord1!'),
        ...$attributes,
    ]));
}

it('shows the profile of a clinic user', function () {
    profileUser();

    $this->withHeader('X-API-KEY', 'valid-api-key-123')
        ->getJson(route('api.clinic.naw.show', 'kc-clinic-1'))
        ->assertOk()
        ->assertExactJson(['first_name' => 'Piet', 'last_name' => 'Jansen', 'name' => 'Piet Jansen', 'email' => 'piet@kliniek.example']);
});

it('does not expose users without a clinic-portal role, inactive users or unknown ids', function (callable $arrange, string $id) {
    $arrange();

    $this->withHeader('X-API-KEY', 'valid-api-key-123')
        ->getJson(route('api.clinic.naw.show', $id))
        ->assertNotFound();
})->with([
    'employee role' => [fn () => profileUser(roleCode: null), 'kc-clinic-1'],
    'inactive'      => [fn () => profileUser(['status' => 0]), 'kc-clinic-1'],
    'unknown id'    => [fn () => profileUser(), 'kc-other'],
]);

it('requires the API key or a Keycloak token', function () {
    profileUser();

    $this->getJson(route('api.clinic.naw.show', 'kc-clinic-1'))->assertUnauthorized();
});

it('updates only the name, never the e-mail address', function () {
    $user = profileUser();

    $this->withHeader('X-API-KEY', 'valid-api-key-123')
        ->putJson(route('api.clinic.naw.update', 'kc-clinic-1'), [
            'first_name' => 'Pieter',
            'last_name'  => 'de Jong',
            'email'      => 'andere@example.com',
        ])
        ->assertNoContent();

    expect($user->refresh())
        ->first_name->toBe('Pieter')
        ->last_name->toBe('de Jong')
        ->email->toBe('piet@kliniek.example');
});

it('validates the name', function () {
    profileUser();

    $this->withHeader('X-API-KEY', 'valid-api-key-123')
        ->putJson(route('api.clinic.naw.update', 'kc-clinic-1'), ['first_name' => '', 'last_name' => 'Jong'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('first_name');
});

it('changes the password when the current one is correct', function () {
    $user = profileUser();

    $this->withHeader('X-API-KEY', 'valid-api-key-123')
        ->putJson(route('api.clinic.password.update', 'kc-clinic-1'), [
            'current_password'      => 'OudWachtwoord1!',
            'password'              => 'NieuwWachtwoord1!',
            'password_confirmation' => 'NieuwWachtwoord1!',
        ])
        ->assertNoContent();

    expect(Hash::check('NieuwWachtwoord1!', $user->refresh()->password))->toBeTrue();
});

it('rejects a wrong or missing current password for API-key callers', function (array $extra) {
    $user = profileUser();

    $this->withHeader('X-API-KEY', 'valid-api-key-123')
        ->putJson(route('api.clinic.password.update', 'kc-clinic-1'), [
            'password'              => 'NieuwWachtwoord1!',
            'password_confirmation' => 'NieuwWachtwoord1!',
            ...$extra,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    expect(Hash::check('OudWachtwoord1!', $user->refresh()->password))->toBeTrue();
})->with([
    'wrong'   => [['current_password' => 'fout']],
    'missing' => [[]],
]);

it('does not let a Bearer token reach the unauthenticated reset route', function () {
    profileUser();

    $this->withHeader('Authorization', 'Bearer some-token')
        ->postJson(route('api.clinic.password.reset', 'kc-clinic-1'), [])
        ->assertUnauthorized();
});
