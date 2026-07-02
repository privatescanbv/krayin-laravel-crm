<?php

use App\Actions\Keycloak\AddKeycloakUserAction;
use App\Actions\Persons\CreatePortalAccountAction;
use App\Services\Mail\CrmMailService;
use App\Services\PatientPortal\PatientPortalPasswordSetupLinkService;
use App\Services\PersonKeycloakService;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Webkul\Contact\Models\Person;
use Webkul\Email\Mails\Email as EmailMailable;
use Webkul\Email\Models\Email;
use Webkul\EmailTemplate\Models\EmailTemplate;
use Webkul\Lead\Models\Lead;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    Mail::fake();

    Config::set('services.keycloak.client_id', 'test-client');
    Config::set('services.portal.patient.web_url', 'https://patient-portal.test');
    Config::set('services.portal.patient.api_url', 'https://patient-portal.test');
    Config::set('services.portal.patient.api_token', 'portal-api-key');
    Config::set('services.portal.patient.password_setup_endpoint', '/api/patient/password-reset-link');

    $passwordSetupLinkService = Mockery::mock(PatientPortalPasswordSetupLinkService::class);
    $passwordSetupLinkService->shouldReceive('fetchForPerson')
        ->andReturn('https://patient.test/patient/reset-password?email=patient%40example.com&token=default-token');
    $this->app->instance(PatientPortalPasswordSetupLinkService::class, $passwordSetupLinkService);
});

function makeCreatePortalAccountAction(
    PersonKeycloakService $personKeycloakService,
    CrmMailService $crmMailService,
): CreatePortalAccountAction {
    return new CreatePortalAccountAction(
        $personKeycloakService,
        $crmMailService,
        app(PatientPortalPasswordSetupLinkService::class),
    );
}

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

    $action = makeCreatePortalAccountAction($personKeycloakService, app(CrmMailService::class));
    $result = $action->execute($person);

    expect($result['success'])->toBeTrue();

    Mail::assertQueued(EmailMailable::class, 1);

    $email = Email::where('person_id', $person->id)->firstOrFail();
    expect($email->subject)->toBe('Welkom bij het Privatescan patiëntportaal');
});

test('welcome mail fills loginUrlWithUsernameHint with portal password-setup link', function () {
    $this->app->forgetInstance(PatientPortalPasswordSetupLinkService::class);

    Http::fake([
        'https://patient-portal.test/api/patient/password-reset-link' => Http::response([
            'reset_url' => 'https://patient.test/patient/reset-password?email=patient%40example.com&token=setup-token',
        ], 201),
    ]);

    EmailTemplate::query()
        ->where('code', 'patient-portal-notification')
        ->update([
            'content' => '<p>{{ loginUrlWithUsernameHint }}</p>',
        ]);

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
            'success'            => true,
            'keycloak_user_id'   => 'kc-user-setup',
            'generated_password' => 'TempPass123!',
        ]);

    $action = makeCreatePortalAccountAction($personKeycloakService, app(CrmMailService::class));
    $result = $action->execute($person);

    expect($result['success'])->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://patient-portal.test/api/patient/password-reset-link'
            && $request->hasHeader('X-API-KEY', 'portal-api-key')
            && ($request['email'] ?? null) === 'patient@example.com'
            && ($request['keycloak_user_id'] ?? null) === 'kc-user-setup';
    });

    $email = Email::where('person_id', $person->id)
        ->where('subject', 'Welkom bij het Privatescan patiëntportaal')
        ->firstOrFail();

    expect(html_entity_decode($email->reply, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
        ->toContain('https://patient.test/patient/reset-password?email=patient%40example.com&token=setup-token');
});

test('returns technical error when portal password-setup link cannot be obtained', function () {
    $this->app->forgetInstance(PatientPortalPasswordSetupLinkService::class);

    Http::fake([
        'https://patient-portal.test/api/patient/password-reset-link' => Http::response([], 500),
    ]);

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
            'success'            => true,
            'keycloak_user_id'   => 'kc-user-fail',
            'generated_password' => 'TempPass123!',
        ]);

    $action = makeCreatePortalAccountAction($personKeycloakService, app(CrmMailService::class));
    $result = $action->execute($person);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('technische fout');

    Mail::assertNothingQueued();
    expect(Email::where('person_id', $person->id)->count())->toBe(0);
});

test('temporary password remains consistent between generated credentials and welcome email', function () {
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

    $action = makeCreatePortalAccountAction($personKeycloakService, app(CrmMailService::class));
    $result = $action->execute($person);

    expect($result['success'])->toBeTrue();

    $email = Email::where('person_id', $person->id)
        ->where('subject', 'Welkom bij het Privatescan patiëntportaal')
        ->firstOrFail();

    expect(html_entity_decode($email->reply, ENT_QUOTES | ENT_HTML5, 'UTF-8'))->toContain($expectedPassword);
});

test('generated password sent to keycloak matches password in welcome email when using real keycloak service', function () {
    /** @var Person $person */
    $person = Person::factory()->create([
        'emails' => [
            ['value' => 'real-flow@example.com', 'label' => 'eigen', 'is_default' => true],
        ],
        'is_active'        => false,
        'keycloak_user_id' => null,
        'password'         => null,
    ]);

    $addKeycloakUserAction = Mockery::mock(AddKeycloakUserAction::class);
    $addKeycloakUserAction->shouldReceive('execute')
        ->once()
        ->andReturnUsing(function (array $userData, string $password, bool $temporary, ?string $role) {
            app()->instance('test_keycloak_password', $password);

            return [
                'success'          => true,
                'keycloak_user_id' => 'kc-real-flow-1',
            ];
        });

    $this->app->instance(AddKeycloakUserAction::class, $addKeycloakUserAction);

    $action = makeCreatePortalAccountAction(app(PersonKeycloakService::class), app(CrmMailService::class));
    $result = $action->execute($person);

    expect($result['success'])->toBeTrue();

    $keycloakPassword = app('test_keycloak_password');

    $email = Email::where('person_id', $person->id)
        ->where('subject', 'Welkom bij het Privatescan patiëntportaal')
        ->firstOrFail();

    expect(html_entity_decode($email->reply, ENT_QUOTES | ENT_HTML5, 'UTF-8'))->toContain($keycloakPassword);
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

    $action = makeCreatePortalAccountAction($personKeycloakService, app(CrmMailService::class));
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

    $action = makeCreatePortalAccountAction($personKeycloakService, app(CrmMailService::class));
    $result = $action->execute($person, null, $lead);

    expect($result['success'])->toBeTrue();

    $emails = Email::where('lead_id', $lead->id)->get();

    expect($emails)->toHaveCount(1);
    expect($emails->first()->subject)->toBe('Welkom bij het Privatescan patiëntportaal');
});
