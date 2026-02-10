<?php

use App\Actions\Persons\CreatePortalAccountAction;
use App\Services\Keycloak\KeycloakService;
use App\Services\Mail\PatientMailService;
use App\Services\PersonKeycloakService;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Webkul\Contact\Models\Person;
use Webkul\Email\Mails\Email as EmailMailable;
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Lead;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    Mail::fake();

    // Zorg dat Keycloak als geconfigureerd wordt gezien.
    Config::set('services.keycloak.client_id', 'test-client');
});

test('sends welcome mail and links email to person when portal account is created', function () {
    /** @var Person $person */
    $person = Person::factory()->create([
        'emails' => [
            ['value' => 'patient@example.com', 'label' => 'eigen', 'is_default' => true],
        ],
        'is_active'        => false,
        'keycloak_user_id' => null,
    ]);

    $personKeycloakService = Mockery::mock(PersonKeycloakService::class);
    $personKeycloakService->shouldReceive('create')
        ->once()
        ->andReturn([
            'success'           => true,
            'keycloak_user_id'  => 'kc-user-1',
            'generated_password'=> 'TempPass123!',
        ]);

    $keycloakService = Mockery::mock(KeycloakService::class);
    $keycloakService->shouldReceive('getRealmLoginUrl')
        ->andReturn('https://sso.local.privatescan.nl/realms/crm/protocol/openid-connect/auth?redirect_uri=test');

    $patientMailService = app(PatientMailService::class);

    $action = new CreatePortalAccountAction($personKeycloakService, $patientMailService);

    $result = $action->execute($person);

    expect($result['success'])->toBeTrue();

    // Verify email was queued using EmailMailable (same as EmailController)
    Mail::assertQueued(EmailMailable::class, function (EmailMailable $mail) use ($person) {
        return $mail->email->person_id === $person->id
            && $mail->email->subject === 'Welkom bij het Privatescan patiëntportaal';
    });

    $emails = Email::where('person_id', $person->id)->get();
    expect($emails)->toHaveCount(1)
        ->and($emails->first()->subject)->toBe('Welkom bij het Privatescan patiëntportaal');
});

test('temporary password in flash message matches password in welcome email', function () {
    /** @var Person $person */
    $person = Person::factory()->create([
        'emails' => [
            ['value' => 'password-check@example.com', 'label' => 'eigen', 'is_default' => true],
        ],
        'is_active'        => false,
        'keycloak_user_id' => null,
        'password'         => null,
    ]);

    $expectedPassword = 'TempPass123!';

    $personKeycloakService = Mockery::mock(PersonKeycloakService::class);
    $personKeycloakService->shouldReceive('create')
        ->once()
        ->andReturn([
            'success'            => true,
            'keycloak_user_id'   => 'kc-user-pw-check',
            'generated_password' => $expectedPassword,
        ]);

    $patientMailService = app(PatientMailService::class);

    $action = new CreatePortalAccountAction($personKeycloakService, $patientMailService);

    $result = $action->execute($person);

    // 1) Wachtwoord in flash/tooltip message
    expect($result['success'])->toBeTrue()
        ->and($result['message'])->toContain($expectedPassword);

    // 2) Wachtwoord in de opgeslagen email body (reply veld)
    $email = Email::where('person_id', $person->id)->firstOrFail();

    expect($email->reply)->toContain($expectedPassword);
});

test('temporary password in flash message matches password in email when using real keycloak service', function () {
    /** @var Person $person */
    $person = Person::factory()->create([
        'emails' => [
            ['value' => 'real-flow@example.com', 'label' => 'eigen', 'is_default' => true],
        ],
        'is_active'        => false,
        'keycloak_user_id' => null,
        'password'         => null,
    ]);

    // Mock alleen de Keycloak HTTP calls, niet de PersonKeycloakService zelf
    $addKeycloakUserAction = Mockery::mock(\App\Actions\Keycloak\AddKeycloakUserAction::class);
    $addKeycloakUserAction->shouldReceive('execute')
        ->once()
        ->andReturnUsing(function (array $userData, string $password, bool $temporary, ?string $role) {
            // Bewaar het wachtwoord dat naar Keycloak gestuurd zou worden
            app()->instance('test_keycloak_password', $password);

            return [
                'success'          => true,
                'keycloak_user_id' => 'kc-real-flow-1',
            ];
        });

    $this->app->instance(\App\Actions\Keycloak\AddKeycloakUserAction::class, $addKeycloakUserAction);

    $personKeycloakService = app(PersonKeycloakService::class);
    $patientMailService = app(PatientMailService::class);

    $action = new CreatePortalAccountAction($personKeycloakService, $patientMailService);

    $result = $action->execute($person);

    expect($result['success'])->toBeTrue();

    // Haal de 3 wachtwoorden op:
    // 1) Wachtwoord gestuurd naar Keycloak API
    $keycloakPassword = app('test_keycloak_password');

    // 2) Wachtwoord in de tooltip/flash message
    preg_match('/Tijdelijk wachtwoord: (.+)$/', $result['message'], $matches);
    $tooltipPassword = $matches[1] ?? null;

    // 3) Wachtwoord in de email body
    $email = Email::where('person_id', $person->id)->firstOrFail();
    preg_match('/<strong>Tijdelijk wachtwoord<\/strong>:<br>\s*<strong>(.+?)<\/strong>/', $email->reply, $emailMatches);
    $emailPassword = $emailMatches[1] ?? null;

    expect($tooltipPassword)->not->toBeNull('Tooltip message moet het wachtwoord bevatten')
        ->and($emailPassword)->not->toBeNull('Email body moet het wachtwoord bevatten')
        ->and($tooltipPassword)->toBe($keycloakPassword, 'Tooltip wachtwoord moet gelijk zijn aan wat naar Keycloak ging')
        ->and($emailPassword)->toBe($keycloakPassword, 'Email wachtwoord moet gelijk zijn aan wat naar Keycloak ging')
        ->and($tooltipPassword)->toBe($emailPassword, 'Tooltip en email wachtwoord moeten identiek zijn');
});

test('links welcome mail to lead when lead is provided', function () {
    /** @var Person $person */
    $person = Person::factory()->create([
        'emails' => [
            ['value' => 'patient2@example.com', 'label' => 'eigen', 'is_default' => true],
        ],
        'is_active'        => false,
        'keycloak_user_id' => null,
    ]);

    /** @var Lead $lead */
    $lead = Lead::factory()->create();

    $personKeycloakService = Mockery::mock(PersonKeycloakService::class);
    $personKeycloakService->shouldReceive('create')
        ->once()
        ->andReturn([
            'success'           => true,
            'keycloak_user_id'  => 'kc-user-2',
            'generated_password'=> 'AnotherTemp123!',
        ]);

    $keycloakService = Mockery::mock(KeycloakService::class);
    $keycloakService->shouldReceive('getRealmLoginUrl')
        ->andReturn('https://sso.local.privatescan.nl/realms/crm/protocol/openid-connect/auth?redirect_uri=test');

    $patientMailService = app(PatientMailService::class);

    $action = new CreatePortalAccountAction($personKeycloakService, $patientMailService);

    $result = $action->execute($person, null, $lead);

    expect($result['success'])->toBeTrue();

    // With the new prioritization logic, if lead_id is set, person_id is removed
    // So we should search by lead_id only
    $emails = Email::where('lead_id', $lead->id)->get();

    expect($emails)->toHaveCount(1)
        ->and($emails->first()->subject)->toBe('Welkom bij het Privatescan patiëntportaal');
});
