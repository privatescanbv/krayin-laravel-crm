<?php

use App\Actions\Persons\CreatePortalAccountAction;
use App\Services\Keycloak\KeycloakService;
use App\Services\Mail\CrmMailService;
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

    $crmMailService = app(CrmMailService::class);

    $action = new CreatePortalAccountAction($personKeycloakService, $crmMailService);

    $result = $action->execute($person);

    expect($result['success'])->toBeTrue();

    Mail::assertQueued(EmailMailable::class, 1);

    $email = Email::where('person_id', $person->id)->firstOrFail();
    expect($email->subject)->toBe('Welkom bij het Privatescan patiëntportaal');
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

    $crmMailService = app(CrmMailService::class);

    $action = new CreatePortalAccountAction($personKeycloakService, $crmMailService);

    $result = $action->execute($person);

    // 1) Wachtwoord in flash/tooltip message
    expect($result['success'])->toBeTrue()
        ->and($result['message'])->toContain($expectedPassword);

    // 2) Wachtwoord in de welkomstmail
    $email = Email::where('person_id', $person->id)
        ->where('subject', 'Welkom bij het Privatescan patiëntportaal')
        ->firstOrFail();

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
    $crmMailService = app(CrmMailService::class);

    $action = new CreatePortalAccountAction($personKeycloakService, $crmMailService);

    $result = $action->execute($person);

    expect($result['success'])->toBeTrue();

    // Haal de 3 wachtwoorden op:
    // 1) Wachtwoord gestuurd naar Keycloak API
    $keycloakPassword = app('test_keycloak_password');

    // 2) Wachtwoord in de tooltip/flash message
    preg_match('/Tijdelijk wachtwoord: (.+)$/', $result['message'], $matches);
    $tooltipPassword = $matches[1] ?? null;

    // 3) Wachtwoord in de welkomstmail body
    $email = Email::where('person_id', $person->id)
        ->where('subject', 'Welkom bij het Privatescan patiëntportaal')
        ->firstOrFail();

    expect($tooltipPassword)->not->toBeNull()
        ->and($tooltipPassword)->toBe($keycloakPassword)
        ->and($email->reply)->toContain($keycloakPassword);
});

test('does not queue mail when sendAccountEmails is false (sync path)', function () {
    /** @var Person $person */
    $person = Person::factory()->create([
        'emails' => [
            ['value' => 'sync-nomail@example.com', 'label' => 'eigen', 'is_default' => true],
        ],
        'is_active'        => false,
        'keycloak_user_id' => null,
    ]);

    $personKeycloakService = Mockery::mock(PersonKeycloakService::class);
    $personKeycloakService->shouldReceive('create')
        ->once()
        ->andReturn([
            'success'            => true,
            'keycloak_user_id'   => 'kc-sync-no-mail',
            'generated_password' => 'SyncPass123!',
        ]);

    $crmMailService = app(CrmMailService::class);

    $action = new CreatePortalAccountAction($personKeycloakService, $crmMailService);

    $result = $action->execute($person, null, null, false);

    expect($result['success'])->toBeTrue();

    Mail::assertNothingQueued();
    expect(Email::where('person_id', $person->id)->count())->toBe(0);
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

    $crmMailService = app(CrmMailService::class);

    $action = new CreatePortalAccountAction($personKeycloakService, $crmMailService);

    $result = $action->execute($person, null, $lead);

    expect($result['success'])->toBeTrue();

    $emails = Email::where('lead_id', $lead->id)->get();

    expect($emails)->toHaveCount(1);
    expect($emails->first()->subject)->toBe('Welkom bij het Privatescan patiëntportaal');
});
