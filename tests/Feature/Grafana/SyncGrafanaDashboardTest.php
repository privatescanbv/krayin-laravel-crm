<?php

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const GF_SOURCE = 'https://gf-local.test';
const GF_TARGET = 'https://gf-prod.test';

beforeEach(function () {
    config()->set('services.grafana.environments', [
        'local' => ['url' => GF_SOURCE, 'user' => 'admin', 'password' => 'secret', 'token' => null],
        'prod'  => ['url' => GF_TARGET, 'token' => 'prod-token', 'user' => null, 'password' => null],
    ]);
});

function grafanaDashboard(): array
{
    return [
        'dashboard' => [
            'id'     => 2,
            'uid'    => 'stack-monitor',
            'title'  => 'Stack monitoring overview',
            'panels' => [[
                'id'         => 11,
                'title'      => 'Traefik access log',
                'datasource' => ['type' => 'loki', 'uid' => 'local-loki'],
                'targets'    => [['datasource' => ['type' => 'loki', 'uid' => 'local-loki'], 'expr' => '{service="traefik"}']],
            ]],
        ],
        'meta' => ['folderUid' => '', 'folderTitle' => 'General'],
    ];
}

/**
 * @param  array<string, mixed>  $overrides  "METHOD url" => response body (or a Http::response())
 */
function fakeGrafana(array $overrides = []): void
{
    $routes = array_merge([
        'GET '.GF_SOURCE.'/api/datasources'                   => [['uid' => 'local-loki', 'name' => 'Loki', 'type' => 'loki']],
        'GET '.GF_TARGET.'/api/datasources'                   => [['uid' => 'prod-loki', 'name' => 'loki-prod', 'type' => 'loki']],
        'GET '.GF_SOURCE.'/api/search'                        => [['uid' => 'stack-monitor', 'title' => 'Stack monitoring overview']],
        'GET '.GF_SOURCE.'/api/dashboards/uid/stack-monitor'  => grafanaDashboard(),
        'GET '.GF_TARGET.'/api/dashboards/uid/stack-monitor'  => Http::response(['message' => 'Dashboard not found'], 404),
        'GET '.GF_TARGET.'/api/folders'                       => [],
        'POST '.GF_TARGET.'/api/folders'                      => ['uid' => 'ops', 'title' => 'Ops'],
        'POST '.GF_TARGET.'/api/dashboards/db'                => ['status' => 'success', 'uid' => 'stack-monitor'],
    ], $overrides);

    Http::fake(function (Request $request) use ($routes) {
        $key = $request->method().' '.strtok($request->url(), '?');

        if (! array_key_exists($key, $routes)) {
            return Http::response(['message' => "unexpected {$key}"], 500);
        }

        $route = $routes[$key];

        return $route instanceof PromiseInterface ? $route : Http::response($route);
    });
}

function savedDashboard(): ?array
{
    $saved = collect(Http::recorded())->first(fn ($pair) => $pair[0]->method() === 'POST' && str_ends_with($pair[0]->url(), '/api/dashboards/db'));

    return $saved ? $saved[0]->data() : null;
}

test('syncs all source dashboards and remaps the datasource uid to the target', function () {
    fakeGrafana();

    $this->artisan('grafana:sync-dashboard', ['--source' => 'local', '--target' => 'prod', '--force' => true])
        ->expectsOutputToContain('datasource local-loki → prod-loki')
        ->expectsOutputToContain('will be created')
        ->assertSuccessful();

    $saved = savedDashboard();

    expect($saved['overwrite'])->toBeTrue()
        ->and($saved['folderUid'])->toBe('')
        ->and($saved['dashboard']['id'])->toBeNull()
        ->and($saved['dashboard']['uid'])->toBe('stack-monitor')
        ->and($saved['dashboard']['panels'][0]['datasource']['uid'])->toBe('prod-loki')
        ->and($saved['dashboard']['panels'][0]['targets'][0]['datasource']['uid'])->toBe('prod-loki');

    Http::assertSent(fn (Request $r) => str_starts_with($r->url(), GF_TARGET) && $r->hasHeader('Authorization', 'Bearer prod-token'));
    Http::assertSent(fn (Request $r) => str_starts_with($r->url(), GF_SOURCE) && $r->hasHeader('Authorization', 'Basic '.base64_encode('admin:secret')));
});

test('dry run reports the overwrite but writes nothing', function () {
    fakeGrafana([
        'GET '.GF_TARGET.'/api/dashboards/uid/stack-monitor' => ['dashboard' => ['version' => 7], 'meta' => ['updated' => '2026-10-01', 'updatedBy' => 'admin']],
    ]);

    $this->artisan('grafana:sync-dashboard', ['--source' => 'local', '--target' => 'prod', '--dashboard' => ['stack-monitor'], '--dry-run' => true])
        ->expectsOutputToContain('will overwrite target version 7')
        ->assertSuccessful();

    expect(savedDashboard())->toBeNull();
});

test('a missing folder is created on the target with the source uid', function () {
    $dashboard = grafanaDashboard();
    $dashboard['meta'] = ['folderUid' => 'ops', 'folderTitle' => 'Ops'];
    fakeGrafana(['GET '.GF_SOURCE.'/api/dashboards/uid/stack-monitor' => $dashboard]);

    $this->artisan('grafana:sync-dashboard', ['--source' => 'local', '--target' => 'prod', '--force' => true])
        ->expectsOutputToContain("folder 'Ops' will be created")
        ->assertSuccessful();

    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/api/folders') && $r['uid'] === 'ops');
    expect(savedDashboard()['folderUid'])->toBe('ops');
});

test('fails the dashboard when the target has no matching datasource', function () {
    fakeGrafana(['GET '.GF_TARGET.'/api/datasources' => [['uid' => 'pg', 'name' => 'Postgres', 'type' => 'postgres']]]);

    $this->artisan('grafana:sync-dashboard', ['--source' => 'local', '--target' => 'prod', '--force' => true])
        ->expectsOutputToContain('No matching datasource on the target for: local-loki')
        ->expectsOutputToContain('Failed dashboards: stack-monitor')
        ->assertFailed();

    expect(savedDashboard())->toBeNull();
});

test('rejects an unknown environment', function () {
    $this->artisan('grafana:sync-dashboard', ['--source' => 'local', '--target' => 'acc'])
        ->expectsOutputToContain("Unknown Grafana environment 'acc'")
        ->assertFailed();
});
