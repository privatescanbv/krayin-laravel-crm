<?php

use App\Enums\RoleCode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

/**
 * MBS-558: the clinic-portal roles (arts, kliniek medewerker) are CRM `User` accounts, not
 * patients. Their own Keycloak id still satisfies the `patient.self` self-match middleware
 * (it only checks identity, not role), so every patient API endpoint must explicitly reject
 * them instead of silently answering with empty data.
 */
function clinicPortalUser(string $keycloakUserId, RoleCode $roleCode): User
{
    $role = Role::factory()->create(['code' => $roleCode->value]);

    return User::withoutEvents(fn () => User::factory()->create([
        'keycloak_user_id' => $keycloakUserId,
        'role_id'          => $role->id,
        'status'           => 1,
        'password'         => Hash::make('Wachtwoord1!'),
    ]));
}

function asClinicPortalBearer(string $keycloakUserId): void
{
    config(['api.keys' => []]);

    $socialiteUser = new SocialiteUser;
    $socialiteUser->setRaw(['sub' => $keycloakUserId]);
    $socialiteUser->map(['id' => $keycloakUserId]);

    $provider = Mockery::mock();
    $provider->shouldReceive('userFromToken')->andReturn($socialiteUser);
    Socialite::shouldReceive('driver')->with('keycloak')->andReturn($provider);
}

it('forbids a clinic-portal user from every patient API call, using their own bearer token', function (RoleCode $roleCode) {
    $keycloakUserId = (string) Str::uuid();
    clinicPortalUser($keycloakUserId, $roleCode);
    asClinicPortalBearer($keycloakUserId);

    $headers = ['Authorization' => 'Bearer test-token'];

    $this->getJson("/api/patient/{$keycloakUserId}/counters", $headers)->assertForbidden();
    $this->getJson("/api/patient/{$keycloakUserId}/appointments", $headers)->assertForbidden();
    $this->getJson("/api/patient/{$keycloakUserId}/appointments/999", $headers)->assertForbidden();
    $this->getJson("/api/patient/{$keycloakUserId}/documents", $headers)->assertForbidden();
    $this->getJson("/api/patient/{$keycloakUserId}/documents/999/download", $headers)->assertForbidden();
    $this->getJson("/api/patient/{$keycloakUserId}/document-types", $headers)->assertForbidden();
    $this->getJson("/api/patient/{$keycloakUserId}/notifications", $headers)->assertForbidden();
    $this->postJson("/api/patient/{$keycloakUserId}/notifications/999/read", [], $headers)->assertForbidden();
    $this->getJson("/api/patient/{$keycloakUserId}/naw", $headers)->assertForbidden();
    $this->putJson("/api/patient/{$keycloakUserId}/naw", [], $headers)->assertForbidden();
    $this->putJson("/api/patient/{$keycloakUserId}/password", [], $headers)->assertForbidden();
    $this->getJson("/api/patient/{$keycloakUserId}/preferences", $headers)->assertForbidden();
    $this->putJson("/api/patient/{$keycloakUserId}/preferences", [], $headers)->assertForbidden();
    $this->getJson("/api/patient/{$keycloakUserId}/messages", $headers)->assertForbidden();
    $this->postJson("/api/patient/{$keycloakUserId}/messages", ['body' => 'hoi'], $headers)->assertForbidden();
    $this->putJson("/api/patient/{$keycloakUserId}/messages/mark_as_read", [], $headers)->assertForbidden();

    $this->post(
        "/api/patient/{$keycloakUserId}/documents",
        ['name' => 'Bloeduitslag', 'file' => UploadedFile::fake()->create('uitslag.pdf', 10, 'application/pdf')],
        $headers
    )->assertForbidden();
})->with([
    'arts'               => [RoleCode::ARTS],
    'kliniek medewerker' => [RoleCode::KLINIEK_MEDEWERKER],
]);
