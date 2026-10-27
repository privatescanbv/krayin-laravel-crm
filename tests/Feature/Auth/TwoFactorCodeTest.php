<?php

use App\Enums\EmailTemplateCode;
use App\Mail\TwoFactorCodeMail;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
