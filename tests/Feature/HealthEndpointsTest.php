<?php

use App\Services\HealthChecker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

const HEALTH_TOKEN = 'a-test-token-of-at-least-32-bytes-long!!';

beforeEach(function () {
    config(['health.token' => HEALTH_TOKEN]);
    $this->withHeaders(['Accept' => 'application/json']);
    Cache::store('file')->flush();
});

function breakDatabase(): void
{
    config(['database.connections.broken' => ['driver' => 'sqlite', 'database' => '/nonexistent/secret-host.sqlite']]);
    config(['health.connection' => 'broken']);
}

it('reports live without touching the database', function () {
    $this->mock(HealthChecker::class)->shouldReceive('collect', 'report')->never();

    $this->get('/health/live')->assertOk()->assertExactJson(['schema_version' => '1', 'status' => 'pass'])
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('stays live when the database is down', function () {
    breakDatabase();

    $this->get('/health/live')->assertOk();
});

it('reports ready from the internal network', function () {
    $this->get('/health/ready')->assertOk()->assertExactJson(['schema_version' => '1', 'status' => 'pass']);
});

it('refuses ready via a proxy and does no work', function () {
    $this->mock(HealthChecker::class)->shouldReceive('collect')->never();

    $this->get('/health/ready', ['X-Forwarded-For' => '10.0.0.5'])->assertForbidden();
    $this->get('/health/ready', ['Forwarded' => 'for=10.0.0.5'])->assertForbidden();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get('/health/ready')->assertForbidden();
});

it('allows ready via the proxy only when probe_allow_proxied is on and the peer is internal', function () {
    config(['health.probe_allow_proxied' => true]);

    $this->get('/health/ready', ['X-Forwarded-For' => '203.0.113.9'])->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->get('/health/ready', ['X-Forwarded-For' => '203.0.113.9'])->assertForbidden();
});

it('fails ready with 503 and no error details when the database is unreachable', function () {
    breakDatabase();

    $response = $this->get('/health/ready')->assertStatus(503)->assertExactJson(['schema_version' => '1', 'status' => 'fail']);

    expect($response->getContent())->not->toContain('secret-host');
});

it('requires the dedicated token for the report and does no work without it', function () {
    $this->mock(HealthChecker::class)->shouldReceive('report')->never();

    $this->get('/health/report')->assertUnauthorized();
    $this->get('/health/report', ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
    config(['health.token' => null]);
    $this->get('/health/report', ['Authorization' => 'Bearer '])->assertUnauthorized();
    config(['health.token' => 'short']);
    $this->get('/health/report', ['Authorization' => 'Bearer short'])->assertUnauthorized();
});

it('returns the detailed report with a valid token', function () {
    $this->get('/health/report', ['Authorization' => 'Bearer '.HEALTH_TOKEN])
        ->assertOk()
        ->assertJsonPath('schema_version', '1')
        ->assertJsonPath('status', 'healthy')
        ->assertJsonPath('checks.0.name', 'database.connectivity')
        ->assertJsonPath('checks.0.required', true)
        ->assertJsonPath('checks.0.observed_value', true);
});

it('returns a critical 503 report without leaking the failure', function () {
    breakDatabase();

    $response = $this->get('/health/report', ['Authorization' => 'Bearer '.HEALTH_TOKEN])
        ->assertStatus(503)
        ->assertJsonPath('status', 'critical')
        ->assertJsonPath('checks.0.observed_value', false)
        ->assertJsonPath('checks.0.message', 'Database unavailable');

    expect($response->getContent())->not->toContain('secret-host');
});

it('uses the failed-jobs monitor thresholds, per queue and in total', function () {
    config(['queue.default' => 'database', 'failed_jobs.warning_threshold' => 5, 'failed_jobs.critical_threshold' => 10]);
    foreach ([...array_fill(0, 6, 'default'), ...array_fill(0, 4, 'other')] as $queue) {
        DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => $queue, 'payload' => '{"secret":"payload"}', 'exception' => 'boom']);
    }

    $this->get('/health/ready')->assertOk();
    $response = $this->get('/health/report', ['Authorization' => 'Bearer '.HEALTH_TOKEN])
        ->assertStatus(503)
        ->assertJsonPath('status', 'critical')
        ->assertJsonPath('checks.1.name', 'queue.failed_total')
        ->assertJsonPath('checks.1.observed_value', 10)
        ->assertJsonPath('checks.1.status', 'critical')
        ->assertJsonPath('checks.1.required', false)
        ->assertJsonPath('checks.2.name', 'queue.default.failed')
        ->assertJsonPath('checks.2.observed_value', 6)
        ->assertJsonPath('checks.2.status', 'warning')
        ->assertJsonPath('checks.3.status', 'healthy');

    expect($response->getContent())->not->toContain('payload')->not->toContain('boom');
});

it('applies thresholds at the boundary with critical first', function () {
    $level = new ReflectionMethod(HealthChecker::class, 'level');
    $th = ['operator' => '>=', 'warning' => 100, 'critical' => 500];
    $check = fn ($v) => $level->invoke(new HealthChecker, $v, $th);

    expect($check(99))->toBe('healthy')->and($check(100))->toBe('warning')->and($check(500))->toBe('critical');
});

it('rejects parameters, bodies and other methods', function () {
    $this->get('/health/ready?connection=mysql')->assertStatus(400);
    $this->post('/health/ready')->assertStatus(405);
});

it('rate limits before doing any work', function () {
    config(['health.max_requests_per_minute' => 2]);

    $this->get('/health/ready')->assertOk();
    $this->get('/health/ready')->assertOk();
    $this->mock(HealthChecker::class)->shouldReceive('collect')->never();
    $this->get('/health/ready')->assertStatus(429);
});
