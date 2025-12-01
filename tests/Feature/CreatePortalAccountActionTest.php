<?php

use App\Actions\Persons\CreatePortalAccountAction;
use App\Mail\PortalWelcomeMail;
use App\Services\Keycloak\KeycloakService;
use App\Services\PersonKeycloakService;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Webkul\Contact\Models\Person;
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

    $action = new CreatePortalAccountAction($personKeycloakService, $keycloakService);

    $result = $action->execute($person);

    expect($result['success'])->toBeTrue();

    Mail::assertQueued(PortalWelcomeMail::class, function (PortalWelcomeMail $mail) use ($person) {
        return $mail->person->id === $person->id
            && $mail->temporaryPassword === 'TempPass123!';
    });

    $emails = Email::where('person_id', $person->id)->get();
    expect($emails)->toHaveCount(1)
        ->and($emails->first()->subject)->toBe('Welkom bij het Privatescan patiëntportaal');
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

    $action = new CreatePortalAccountAction($personKeycloakService, $keycloakService);

    $result = $action->execute($person, null, $lead);

    expect($result['success'])->toBeTrue();

    $emails = Email::where('person_id', $person->id)
        ->where('lead_id', $lead->id)
        ->get();

    expect($emails)->toHaveCount(1);
});
