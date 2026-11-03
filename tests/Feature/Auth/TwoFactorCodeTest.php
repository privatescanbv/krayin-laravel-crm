<?php

use App\Enums\EmailTemplateCode;
use App\Mail\TwoFactorCodeMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email;
use Webkul\EmailTemplate\Models\EmailTemplate;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123']]);
    $this->seed(EmailTemplateSeeder::class);
    Mail::fake();
});

it('mails the 2fa code to the given address', function () {
    $this->withHeader('X-API-KEY', 'valid-api-key-123')
        ->postJson(route('api.two-factor.code'), ['email' => 'medewerker@example.com', 'code' => '123456'])
        ->assertOk();

    Mail::assertSent(TwoFactorCodeMail::class, fn (TwoFactorCodeMail $mail) => $mail->hasTo('medewerker@example.com')
        && str_contains($mail->renderedHtml, '123456')
        && $mail->subjectLine === 'Uw verificatiecode');
});

it('stores a copy on the person of the keycloak user', function () {
    $person = Person::factory()->create(['keycloak_user_id' => 'kc-patient-1']);

    $this->withHeader('X-API-KEY', 'valid-api-key-123')
        ->postJson(route('api.two-factor.code'), ['email' => 'patient@example.com', 'code' => '123456', 'keycloak_user_id' => 'kc-patient-1'])
        ->assertOk();

    $email = Email::query()->where('person_id', $person->id)->sole();

    Mail::assertSent(TwoFactorCodeMail::class, fn (TwoFactorCodeMail $mail) => str_contains($mail->renderedHtml, '123456'));
    expect($email->subject)->toBe('Uw verificatiecode')
        ->and($email->source)->toBe('system')
        ->and($email->reply)->toContain('123456');
});

it('stores nothing without a matching person', function (?string $keycloakUserId) {
    $this->withHeader('X-API-KEY', 'valid-api-key-123')
        ->postJson(route('api.two-factor.code'), array_filter(['email' => 'medewerker@example.com', 'code' => '123456', 'keycloak_user_id' => $keycloakUserId]))
        ->assertOk();

    Mail::assertSent(TwoFactorCodeMail::class);
    expect(Email::query()->count())->toBe(0);
})->with([
    'no keycloak id'      => [null],
    'unknown keycloak id' => ['kc-employee-1'],
]);

it('rejects invalid input', function (array $payload, string $field) {
    $this->withHeader('X-API-KEY', 'valid-api-key-123')
        ->postJson(route('api.two-factor.code'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    Mail::assertNothingSent();
})->with([
    'missing email'   => [['code' => '123456'], 'email'],
    'invalid email'   => [['email' => 'geen-email', 'code' => '123456'], 'email'],
    'missing code'    => [['email' => 'a@example.com'], 'code'],
    'non-numeric'     => [['email' => 'a@example.com', 'code' => 'abcdef'], 'code'],
    'too short code'  => [['email' => 'a@example.com', 'code' => '123'], 'code'],
]);

it('requires an api key', function () {
    $this->postJson(route('api.two-factor.code'), ['email' => 'a@example.com', 'code' => '123456'])
        ->assertUnauthorized();

    Mail::assertNothingSent();
});

it('rejects a keycloak bearer token', function () {
    $this->withToken('some-patient-token')
        ->postJson(route('api.two-factor.code'), ['email' => 'a@example.com', 'code' => '123456'])
        ->assertUnauthorized();

    Mail::assertNothingSent();
});

it('returns 500 when the template is missing', function () {
    EmailTemplate::query()->where('code', EmailTemplateCode::TWO_FACTOR_CODE->value)->delete();

    $this->withHeader('X-API-KEY', 'valid-api-key-123')
        ->postJson(route('api.two-factor.code'), ['email' => 'a@example.com', 'code' => '123456'])
        ->assertServerError();

    Mail::assertNothingSent();
});

it('adds the template via migration so deploys without seeding get it', function () {
    EmailTemplate::query()->where('code', EmailTemplateCode::TWO_FACTOR_CODE->value)->delete();

    (require database_path('migrations/2026_10_27_000000_add_two_factor_code_email_template.php'))->up();

    expect(EmailTemplate::query()->where('code', EmailTemplateCode::TWO_FACTOR_CODE->value)->count())->toBe(1);
});
