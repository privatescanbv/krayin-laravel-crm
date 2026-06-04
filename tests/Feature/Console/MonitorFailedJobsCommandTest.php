<?php

use App\Mail\FailedJobsCriticalAlert;
use App\Mail\FailedJobsWarningAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Insert N fake failed_jobs rows for testing.
 */
function insertFailedJobs(int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        DB::table('failed_jobs')->insert([
            'uuid'       => Str::uuid()->toString(),
            'connection' => 'sync',
            'queue'      => 'default',
            'payload'    => json_encode(['job' => 'TestJob', 'data' => []]),
            'exception'  => 'RuntimeException: test',
            'failed_at'  => now(),
        ]);
    }
}

// ─── Disabled when no email configured ──────────────────────────────────────

test('does nothing when FAILED_JOBS_ALERT_EMAIL is empty', function () {
    Mail::fake();
    config(['failed_jobs.alert_email' => '']);

    insertFailedJobs(20);

    $exitCode = Artisan::call('queue:monitor-failed-jobs');

    expect($exitCode)->toBe(0);
    Mail::assertNothingSent();
});

// ─── Below threshold ─────────────────────────────────────────────────────────

test('sends no mail when failed jobs count is below warning threshold', function () {
    Mail::fake();
    config([
        'failed_jobs.alert_email'         => 'ops@example.com',
        'failed_jobs.warning_threshold'   => 5,
        'failed_jobs.critical_threshold'  => 10,
    ]);

    insertFailedJobs(3);

    $exitCode = Artisan::call('queue:monitor-failed-jobs');

    expect($exitCode)->toBe(0);
    Mail::assertNothingSent();
});

// ─── Warning threshold ───────────────────────────────────────────────────────

test('sends warning mail when count equals warning threshold', function () {
    Mail::fake();
    config([
        'failed_jobs.alert_email'         => 'ops@example.com',
        'failed_jobs.warning_threshold'   => 5,
        'failed_jobs.critical_threshold'  => 10,
    ]);

    insertFailedJobs(5);

    Artisan::call('queue:monitor-failed-jobs');

    Mail::assertSent(FailedJobsWarningAlert::class);
    Mail::assertNotSent(FailedJobsCriticalAlert::class);
});

test('sends warning mail when count is between warning and critical thresholds', function () {
    Mail::fake();
    config([
        'failed_jobs.alert_email'         => 'ops@example.com',
        'failed_jobs.warning_threshold'   => 5,
        'failed_jobs.critical_threshold'  => 10,
    ]);

    insertFailedJobs(7);

    Artisan::call('queue:monitor-failed-jobs');

    Mail::assertSent(FailedJobsWarningAlert::class, 1);
    Mail::assertNotSent(FailedJobsCriticalAlert::class);
});

test('sends warning mail to all configured recipients', function () {
    Mail::fake();
    config([
        'failed_jobs.alert_email'         => 'ops@example.com,dev@example.com',
        'failed_jobs.warning_threshold'   => 5,
        'failed_jobs.critical_threshold'  => 10,
    ]);

    insertFailedJobs(6);

    Artisan::call('queue:monitor-failed-jobs');

    Mail::assertSent(FailedJobsWarningAlert::class, 2);
});

// ─── Critical threshold ──────────────────────────────────────────────────────

test('sends critical mail when count equals critical threshold', function () {
    Mail::fake();
    config([
        'failed_jobs.alert_email'         => 'ops@example.com',
        'failed_jobs.warning_threshold'   => 5,
        'failed_jobs.critical_threshold'  => 10,
    ]);

    insertFailedJobs(10);

    Artisan::call('queue:monitor-failed-jobs');

    Mail::assertSent(FailedJobsCriticalAlert::class);
    Mail::assertNotSent(FailedJobsWarningAlert::class);
});

test('sends critical mail when count exceeds critical threshold', function () {
    Mail::fake();
    config([
        'failed_jobs.alert_email'         => 'ops@example.com',
        'failed_jobs.warning_threshold'   => 5,
        'failed_jobs.critical_threshold'  => 10,
    ]);

    insertFailedJobs(15);

    Artisan::call('queue:monitor-failed-jobs');

    Mail::assertSent(FailedJobsCriticalAlert::class, 1);
    Mail::assertNotSent(FailedJobsWarningAlert::class);
});

test('sends critical mail to all configured recipients', function () {
    Mail::fake();
    config([
        'failed_jobs.alert_email'         => 'ops@example.com,dev@example.com,manager@example.com',
        'failed_jobs.warning_threshold'   => 5,
        'failed_jobs.critical_threshold'  => 10,
    ]);

    insertFailedJobs(12);

    Artisan::call('queue:monitor-failed-jobs');

    Mail::assertSent(FailedJobsCriticalAlert::class, 3);
});

// ─── Cache throttling ────────────────────────────────────────────────────────

test('does not send duplicate warning mail within 30-minute throttle window', function () {
    Mail::fake();
    config([
        'failed_jobs.alert_email'         => 'ops@example.com',
        'failed_jobs.warning_threshold'   => 5,
        'failed_jobs.critical_threshold'  => 10,
    ]);

    Cache::put('failed_jobs.alert.warning', true, now()->addMinutes(30));

    insertFailedJobs(6);

    Artisan::call('queue:monitor-failed-jobs');

    Mail::assertNothingSent();
});

test('does not send duplicate critical mail within 30-minute throttle window', function () {
    Mail::fake();
    config([
        'failed_jobs.alert_email'         => 'ops@example.com',
        'failed_jobs.warning_threshold'   => 5,
        'failed_jobs.critical_threshold'  => 10,
    ]);

    Cache::put('failed_jobs.alert.critical', true, now()->addMinutes(30));

    insertFailedJobs(12);

    Artisan::call('queue:monitor-failed-jobs');

    Mail::assertNothingSent();
});

test('sets cache after sending warning alert to prevent duplicate mails', function () {
    Mail::fake();
    config([
        'failed_jobs.alert_email'         => 'ops@example.com',
        'failed_jobs.warning_threshold'   => 5,
        'failed_jobs.critical_threshold'  => 10,
    ]);

    insertFailedJobs(6);

    Artisan::call('queue:monitor-failed-jobs');

    expect(Cache::has('failed_jobs.alert.warning'))->toBeTrue();
});

test('sets cache after sending critical alert to prevent duplicate mails', function () {
    Mail::fake();
    config([
        'failed_jobs.alert_email'         => 'ops@example.com',
        'failed_jobs.warning_threshold'   => 5,
        'failed_jobs.critical_threshold'  => 10,
    ]);

    insertFailedJobs(12);

    Artisan::call('queue:monitor-failed-jobs');

    expect(Cache::has('failed_jobs.alert.critical'))->toBeTrue();
});

// ─── Cache reset ─────────────────────────────────────────────────────────────

test('clears cache keys when count drops below thresholds', function () {
    Mail::fake();
    config([
        'failed_jobs.alert_email'         => 'ops@example.com',
        'failed_jobs.warning_threshold'   => 5,
        'failed_jobs.critical_threshold'  => 10,
    ]);

    Cache::put('failed_jobs.alert.warning', true, now()->addMinutes(30));
    Cache::put('failed_jobs.alert.critical', true, now()->addMinutes(30));

    insertFailedJobs(2);

    Artisan::call('queue:monitor-failed-jobs');

    expect(Cache::has('failed_jobs.alert.warning'))->toBeFalse();
    expect(Cache::has('failed_jobs.alert.critical'))->toBeFalse();
    Mail::assertNothingSent();
});

test('clears critical cache when count drops to warning level', function () {
    Mail::fake();
    config([
        'failed_jobs.alert_email'         => 'ops@example.com',
        'failed_jobs.warning_threshold'   => 5,
        'failed_jobs.critical_threshold'  => 10,
    ]);

    Cache::put('failed_jobs.alert.critical', true, now()->addMinutes(30));

    insertFailedJobs(7);

    Artisan::call('queue:monitor-failed-jobs');

    expect(Cache::has('failed_jobs.alert.critical'))->toBeFalse();
});

// ─── Mailable content ────────────────────────────────────────────────────────

test('warning mailable carries correct failed job count', function () {
    Mail::fake();
    config([
        'failed_jobs.alert_email'         => 'ops@example.com',
        'failed_jobs.warning_threshold'   => 5,
        'failed_jobs.critical_threshold'  => 10,
    ]);

    insertFailedJobs(7);

    Artisan::call('queue:monitor-failed-jobs');

    Mail::assertSent(FailedJobsWarningAlert::class, function (FailedJobsWarningAlert $mail) {
        return $mail->failedJobCount === 7;
    });
});

test('critical mailable carries correct failed job count', function () {
    Mail::fake();
    config([
        'failed_jobs.alert_email'         => 'ops@example.com',
        'failed_jobs.warning_threshold'   => 5,
        'failed_jobs.critical_threshold'  => 10,
    ]);

    insertFailedJobs(12);

    Artisan::call('queue:monitor-failed-jobs');

    Mail::assertSent(FailedJobsCriticalAlert::class, function (FailedJobsCriticalAlert $mail) {
        return $mail->failedJobCount === 12;
    });
});

test('warning mailable subject contains environment and job count', function () {
    config(['app.env' => 'testing']);

    $mailable = new FailedJobsWarningAlert(7);
    $mailable->build();

    expect($mailable->subject)->toContain('7')
        ->and($mailable->subject)->toContain('testing');
});

test('critical mailable subject contains environment and job count', function () {
    config(['app.env' => 'testing']);

    $mailable = new FailedJobsCriticalAlert(12);
    $mailable->build();

    expect($mailable->subject)->toContain('12')
        ->and($mailable->subject)->toContain('testing');
});

test('warning mailable exposes environment and timestamp properties', function () {
    $mailable = new FailedJobsWarningAlert(5);

    expect($mailable->environment)->not->toBeEmpty()
        ->and($mailable->timestamp)->not->toBeEmpty();
});

test('critical mailable exposes environment and timestamp properties', function () {
    $mailable = new FailedJobsCriticalAlert(10);

    expect($mailable->environment)->not->toBeEmpty()
        ->and($mailable->timestamp)->not->toBeEmpty();
});
