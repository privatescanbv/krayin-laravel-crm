<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Webkul\Contact\Models\Person;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('services.keycloak.client_id', 'crm-app');
    Config::set('services.keycloak.admin_password', 'test-admin-password');
    Config::set('services.portal.patient.web_url', 'https://patient-portal.test');

    Http::fake(function (HttpRequest $request) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';

        if ($path === '/realms/master/protocol/openid-connect/token') {
            return Http::response(['access_token' => 'test-admin-token'], 200);
        }

        if (preg_match('#^/admin/realms/[^/]+/users/[^/]+/reset-password$#', $path)) {
            return Http::response(null, 204);
        }

        if (preg_match('#^/admin/realms/[^/]+/users/[^/]+$#', $path)) {
            return Http::response(['id' => basename($path)], 200);
        }

        return Http::response([], 200);
    });

    // PersonObserver valt terug op user_id=1 als niemand ingelogd is.
    // Maak een user aan zodat de FK-constraint op activities.user_id klopt.
    makeUser();
});

it('synct het nieuwe wachtwoord naar keycloak na een forgot-password reset', function () {
    $keycloakUserId = (string) Str::uuid();
    $email = 'patient@example.com';

    Person::factory()->create([
        'keycloak_user_id' => $keycloakUserId,
        'is_active'        => true,
        'emails'           => [['value' => $email, 'label' => 'home', 'is_default' => true]],
    ]);

    $url = URL::temporarySignedRoute(
        'patient.reset-password',
        now()->addHour(),
        ['email' => $email]
    );

    $response = $this->post($url, [
        'password'              => 'NieuwWachtwoord1!',
        'password_confirmation' => 'NieuwWachtwoord1!',
    ]);

    $response->assertRedirect();

    Http::assertSent(function (HttpRequest $request) use ($keycloakUserId) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
        $data = $request->data();

        return $request->method() === 'PUT'
            && preg_match('#^/admin/realms/[^/]+/users/'.preg_quote($keycloakUserId, '#').'/reset-password$#', $path) === 1
            && ($data['value'] ?? null) === 'NieuwWachtwoord1!'
            && ($data['temporary'] ?? null) === false;
    });
});

it('slaat het nieuwe wachtwoord versleuteld op in de database', function () {
    $keycloakUserId = (string) Str::uuid();
    $email = 'patient@example.com';

    $person = Person::factory()->create([
        'keycloak_user_id' => $keycloakUserId,
        'is_active'        => true,
        'emails'           => [['value' => $email, 'label' => 'home', 'is_default' => true]],
    ]);

    $url = URL::temporarySignedRoute(
        'patient.reset-password',
        now()->addHour(),
        ['email' => $email]
    );

    $this->post($url, [
        'password'              => 'NieuwWachtwoord1!',
        'password_confirmation' => 'NieuwWachtwoord1!',
    ]);

    $person->refresh();
    expect($person->getDecryptedPassword())->toBe('NieuwWachtwoord1!');
});

it('roept keycloak niet aan als het wachtwoord de validatie niet doorstaat', function () {
    $keycloakUserId = (string) Str::uuid();
    $email = 'patient@example.com';

    Person::factory()->create([
        'keycloak_user_id' => $keycloakUserId,
        'is_active'        => true,
        'emails'           => [['value' => $email, 'label' => 'home', 'is_default' => true]],
    ]);

    $url = URL::temporarySignedRoute(
        'patient.reset-password',
        now()->addHour(),
        ['email' => $email]
    );

    $response = $this->post($url, [
        'password'              => '12345678', // geen hoofdletter, geen speciaal teken
        'password_confirmation' => '12345678',
    ]);

    $response->assertSessionHasErrors(['password']);

    Http::assertNotSent(function (HttpRequest $request) {
        return str_contains($request->url(), '/reset-password');
    });
});

it('roept keycloak niet aan als de person geen keycloak-account heeft', function () {
    $email = 'patient@example.com';

    Person::factory()->create([
        'keycloak_user_id' => null,
        'is_active'        => true,
        'emails'           => [['value' => $email, 'label' => 'home', 'is_default' => true]],
    ]);

    $url = URL::temporarySignedRoute(
        'patient.reset-password',
        now()->addHour(),
        ['email' => $email]
    );

    $response = $this->post($url, [
        'password'              => 'NieuwWachtwoord1!',
        'password_confirmation' => 'NieuwWachtwoord1!',
    ]);

    $response->assertRedirect(route('patient.forgot-password.create'));

    Http::assertNotSent(function (HttpRequest $request) {
        return str_contains($request->url(), '/reset-password');
    });
});

it('geeft 403 terug bij een verlopen signed url', function () {
    $email = 'patient@example.com';

    $url = URL::temporarySignedRoute(
        'patient.reset-password',
        now()->subMinute(),
        ['email' => $email]
    );

    $response = $this->post($url, [
        'password'              => 'NieuwWachtwoord1!',
        'password_confirmation' => 'NieuwWachtwoord1!',
    ]);

    $response->assertStatus(403);

    Http::assertNotSent(function (HttpRequest $request) {
        return str_contains($request->url(), '/reset-password');
    });
});
