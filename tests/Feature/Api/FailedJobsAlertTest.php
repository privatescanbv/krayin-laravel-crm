<?php

use App\Mail\FailedJobsCriticalAlert;
use App\Mail\FailedJobsWarningAlert;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config([
        'api.keys'                => ['valid-api-key-123'],
        'failed_jobs.alert_email' => 'ops@example.com, dev@example.com',
    ]);
    Mail::fake();
});

it('mails a warning alert to every configured recipient', function () {
    $this->withHeader('X-API-KEY', 'valid-api-key-123')
        ->postJson(route('api.failed-jobs.alert'), ['level' => 'warning', 'count' => 7, 'app' => 'PrivateScan Forms'])
        ->assertOk();

    Mail::assertSent(FailedJobsWarningAlert::class, 2);
    Mail::assertSent(FailedJobsWarningAlert::class, fn (FailedJobsWarningAlert $mail) => $mail->hasTo('ops@example.com')
        && $mail->failedJobCount === 7
        && $mail->appName === 'PrivateScan Forms');
    Mail::assertNotSent(FailedJobsCriticalAlert::class);
});

it('mails a critical alert', function () {
    $this->withHeader('X-API-KEY', 'valid-api-key-123')
        ->postJson(route('api.failed-jobs.alert'), ['level' => 'critical', 'count' => 12, 'app' => 'PrivateScan Forms'])
        ->assertOk();

    Mail::assertSent(FailedJobsCriticalAlert::class, fn (FailedJobsCriticalAlert $mail) => $mail->hasTo('dev@example.com')
        && $mail->appName === 'PrivateScan Forms');
    Mail::assertNotSent(FailedJobsWarningAlert::class);
});

it('sends nothing when no recipients are configured', function () {
    config(['failed_jobs.alert_email' => '']);

    $this->withHeader('X-API-KEY', 'valid-api-key-123')
        ->postJson(route('api.failed-jobs.alert'), ['level' => 'warning', 'count' => 7, 'app' => 'PrivateScan Forms'])
        ->assertOk();

    Mail::assertNothingSent();
});

it('rejects invalid input', function (array $payload, string $field) {
    $this->withHeader('X-API-KEY', 'valid-api-key-123')
        ->postJson(route('api.failed-jobs.alert'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    Mail::assertNothingSent();
})->with([
    'unknown level'  => [['level' => 'info', 'count' => 1, 'app' => 'x'], 'level'],
    'missing count'  => [['level' => 'warning', 'app' => 'x'], 'count'],
    'negative count' => [['level' => 'warning', 'count' => -1, 'app' => 'x'], 'count'],
    'missing app'    => [['level' => 'warning', 'count' => 1], 'app'],
]);

it('requires an api key', function () {
    $this->postJson(route('api.failed-jobs.alert'), ['level' => 'warning', 'count' => 7, 'app' => 'x'])
        ->assertUnauthorized();

    Mail::assertNothingSent();
});
