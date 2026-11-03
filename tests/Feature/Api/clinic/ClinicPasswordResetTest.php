<?php

use App\Enums\RoleCode;
use App\Mail\ClinicForgotPasswordMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

const CLINIC_PORTAL = 'https://clinic-portal.test';

beforeEach(function () {
    config([
        'api.keys'                         => ['valid-api-key-123'],
        'services.portal.clinic.api_url'   => CLINIC_PORTAL,
        'services.portal.clinic.api_token' => 'clinic-api-key',
    ]);
    $this->seed(EmailTemplateSeeder::class);
    Mail::fake();

    $this->portalVerifies = true;
    $this->portalCreatesLink = true;
    Http::fake(function (HttpRequest $request) {
        return match ($request->url()) {
            CLINIC_PORTAL.'/api/password-reset-link'   => $this->portalCreatesLink
                ? Http::response(['reset_url' => CLINIC_PORTAL.'/reset-password?token=abc'], 201)
                : Http::response(null, 500),
            CLINIC_PORTAL.'/api/password-reset/verify' => Http::response(null, $this->portalVerifies ? 204 : 404),
            // Keycloak admin API, called by UserObserver when the password changes.
            default => Http::response(['access_token' => 'test-admin-token'], 200),
        };
    });
});

function clinicUser(array $attributes = [], RoleCode|string|null $roleCode = RoleCode::KLINIEK_MEDEWERKER): User
{
    $role = Role::factory()->create(['code' => $roleCode instanceof RoleCode ? $roleCode->value : $roleCode]);

    return User::withoutEvents(fn () => User::factory()->create([
        'email'            => 'kliniek@example.com',
        'status'           => 1,
        'keycloak_user_id' => 'kc-clinic-1',
        'role_id'          => $role->id,
        ...$attributes,
    ]));
}

function requestClinicReset(string $email = 'kliniek@example.com')
{
    return test()->withHeader('X-API-KEY', 'valid-api-key-123')
        ->postJson(route('api.clinic.forgot-password'), ['email' => $email]);
}

function resetClinicPassword(array $headers = [], string $keycloakUserId = 'kc-clinic-1')
{
    return test()->withHeaders(['X-API-KEY' => 'valid-api-key-123', ...$headers])
        ->postJson(route('api.clinic.password.reset', $keycloakUserId), [
            'email'                 => 'kliniek@example.com',
            'reset_token'           => 'portal-token',
            'password'              => 'NieuwWachtwoord1!',
            'password_confirmation' => 'NieuwWachtwoord1!',
        ]);
}

it('mails the portal reset link to a clinic user', function (RoleCode $roleCode) {
    clinicUser(roleCode: $roleCode);

    requestClinicReset()->assertOk();

    Http::assertSent(fn (HttpRequest $request) => $request->url() === CLINIC_PORTAL.'/api/password-reset-link'
        && $request->hasHeader('X-API-KEY', 'clinic-api-key')
        && $request['email'] === 'kliniek@example.com'
        && $request['keycloak_user_id'] === 'kc-clinic-1');

    Mail::assertSent(ClinicForgotPasswordMail::class, fn (ClinicForgotPasswordMail $mail) => $mail->hasTo('kliniek@example.com')
        && str_contains($mail->renderedHtml, CLINIC_PORTAL.'/reset-password?token=abc')
        && $mail->subjectLine === 'Wachtwoord resetten voor het kliniekportaal');
})->with([RoleCode::KLINIEK_MEDEWERKER, RoleCode::ARTS]);

it('answers the same but mails nothing for addresses that are no active clinic user', function (array $attributes, ?string $roleCode) {
    clinicUser($attributes, $roleCode);

    requestClinicReset()->assertOk()->assertJsonPath('message', fn (string $message) => str_starts_with($message, 'Als dit e-mailadres'));

    Mail::assertNothingSent();
    Http::assertNotSent(fn (HttpRequest $request) => str_starts_with($request->url(), CLINIC_PORTAL));
})->with([
    'unknown email'       => [['email' => 'iemand@example.com'], RoleCode::KLINIEK_MEDEWERKER->value],
    'employee role'       => [[], null],
    'inactive user'       => [['status' => 0], RoleCode::KLINIEK_MEDEWERKER->value],
    'no keycloak account' => [['keycloak_user_id' => null], RoleCode::KLINIEK_MEDEWERKER->value],
]);

it('mails nothing when the portal cannot create a link', function () {
    clinicUser();
    $this->portalCreatesLink = false;

    requestClinicReset()->assertOk();

    Mail::assertNothingSent();
});

it('validates the email', function () {
    $this->withHeader('X-API-KEY', 'valid-api-key-123')
        ->postJson(route('api.clinic.forgot-password'), ['email' => 'geen-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('email');
});

it('sets the new password after the portal confirms the token', function () {
    $user = clinicUser();

    resetClinicPassword()->assertNoContent();

    Http::assertSent(fn (HttpRequest $request) => $request->url() === CLINIC_PORTAL.'/api/password-reset/verify'
        && $request->hasHeader('X-API-KEY', 'clinic-api-key')
        && $request['reset_token'] === 'portal-token'
        && $request['keycloak_user_id'] === 'kc-clinic-1');

    expect(Hash::check('NieuwWachtwoord1!', $user->fresh()->password))->toBeTrue();
});

it('refuses the reset when the portal rejects the token', function () {
    $user = clinicUser();
    $this->portalVerifies = false;

    resetClinicPassword()->assertForbidden();

    expect(Hash::check('NieuwWachtwoord1!', $user->fresh()->password))->toBeFalse();
});

it('returns 404 when the keycloak user is no clinic user', function () {
    clinicUser(roleCode: null);

    resetClinicPassword()->assertNotFound();
});

it('refuses a Bearer token on the reset endpoint', function () {
    clinicUser();

    resetClinicPassword(['Authorization' => 'Bearer some-token'])->assertForbidden();

    Http::assertNotSent(fn (HttpRequest $request) => str_starts_with($request->url(), CLINIC_PORTAL));
});

it('validates the new password', function () {
    clinicUser();

    $this->withHeader('X-API-KEY', 'valid-api-key-123')
        ->postJson(route('api.clinic.password.reset', 'kc-clinic-1'), [
            'email'                 => 'kliniek@example.com',
            'reset_token'           => 'portal-token',
            'password'              => 'NieuwWachtwoord1!',
            'password_confirmation' => 'Anders1!',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('password');
});
